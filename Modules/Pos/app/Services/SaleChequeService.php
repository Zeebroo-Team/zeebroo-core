<?php

declare(strict_types=1);

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleCheque;

/**
 * Customer cheques taken at the POS (Sales → Cheques, Customers → Cheques).
 * Claiming a cheque deposits its amount into an account; until then the bill
 * is paid but the money is not in any account.
 */
class SaleChequeService
{
    public function __construct(
        private readonly SalePaymentSettlementService $payments,
        private readonly PosSettingsService $posSettings,
    ) {
    }

    /**
     * @param  array{q?: string, status?: string, customer_id?: int|string|null, date_from?: string, date_to?: string}  $filters
     */
    public function indexForBusiness(Business $business, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = SaleCheque::query()
            ->where('business_id', $business->id)
            ->with(['sale:id,sale_number,status,total,sold_at', 'customer:id,name,phone', 'depositAccount', 'claimedBy:id,name']);

        $this->applyFilters($query, $filters);

        // Pending first (oldest cheque date first — those are due to be banked), then the rest newest first
        return $query
            ->orderByRaw('CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END', [SaleCheque::STATUS_PENDING, SaleCheque::STATUS_BOUNCED])
            ->orderByRaw('CASE WHEN status = ? THEN cheque_date END ASC', [SaleCheque::STATUS_PENDING])
            ->orderByDesc('cheque_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Totals ignore the status filter so the KPI bar always shows the whole picture.
     *
     * @param  array{q?: string, status?: string, customer_id?: int|string|null, date_from?: string, date_to?: string}  $filters
     * @return array{pending_count: int, pending_total: float, due_count: int, due_total: float, cleared_count: int, cleared_total: float, bounced_count: int, bounced_total: float}
     */
    public function summaryForBusiness(Business $business, array $filters = []): array
    {
        $query = SaleCheque::query()->where('business_id', $business->id);
        $this->applyFilters($query, array_merge($filters, ['status' => 'all']));

        $today = now()->toDateString();
        $row = $query->selectRaw(
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,
             SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as pending_total,
             SUM(CASE WHEN status = ? AND cheque_date <= ? THEN 1 ELSE 0 END) as due_count,
             SUM(CASE WHEN status = ? AND cheque_date <= ? THEN amount ELSE 0 END) as due_total,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cleared_count,
             SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as cleared_total,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as bounced_count,
             SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as bounced_total',
            [
                SaleCheque::STATUS_PENDING, SaleCheque::STATUS_PENDING,
                SaleCheque::STATUS_PENDING, $today, SaleCheque::STATUS_PENDING, $today,
                SaleCheque::STATUS_CLEARED, SaleCheque::STATUS_CLEARED,
                SaleCheque::STATUS_BOUNCED, SaleCheque::STATUS_BOUNCED,
            ]
        )->first();

        return [
            'pending_count' => (int) ($row->pending_count ?? 0),
            'pending_total' => round((float) ($row->pending_total ?? 0), 2),
            'due_count'     => (int) ($row->due_count ?? 0),
            'due_total'     => round((float) ($row->due_total ?? 0), 2),
            'cleared_count' => (int) ($row->cleared_count ?? 0),
            'cleared_total' => round((float) ($row->cleared_total ?? 0), 2),
            'bounced_count' => (int) ($row->bounced_count ?? 0),
            'bounced_total' => round((float) ($row->bounced_total ?? 0), 2),
        ];
    }

    /**
     * Claim (deposit) a cheque: its amount goes into the chosen account — or the POS
     * default deposit account — and the bill is marked settled.
     */
    public function claim(SaleCheque $cheque, Business $business, User $user, ?int $accountId, ?string $notes = null): SaleCheque
    {
        if ((int) $cheque->business_id !== (int) $business->id) {
            abort(404);
        }

        if ($accountId === null) {
            $default   = $this->posSettings->forBusiness($business)['default_deposit_account_id'] ?? null;
            $accountId = is_numeric($default) ? (int) $default : null;
        }

        if ($accountId === null) {
            throw ValidationException::withMessages([
                'deposit_account_id' => 'Choose the account this cheque is deposited into.',
            ]);
        }

        return DB::transaction(function () use ($cheque, $business, $user, $accountId, $notes): SaleCheque {
            // Lock so two people can't claim the same cheque twice
            $cheque = SaleCheque::query()->whereKey($cheque->id)->lockForUpdate()->firstOrFail();

            if (! $cheque->isClaimable()) {
                throw ValidationException::withMessages([
                    'cheque' => $cheque->status === SaleCheque::STATUS_CLEARED
                        ? 'This cheque has already been claimed.'
                        : 'This cheque was cancelled and cannot be claimed.',
                ]);
            }

            /** @var Sale $sale */
            $sale = Sale::query()->whereKey($cheque->pos_sale_id)->lockForUpdate()->firstOrFail();
            if ($sale->isVoid()) {
                throw ValidationException::withMessages([
                    'cheque' => 'The bill for this cheque was reverted — hand the cheque back instead.',
                ]);
            }

            $this->payments->settle(
                $sale,
                $business,
                $user,
                $accountId,
                round((float) $cheque->amount, 2),
                Sale::PAYMENT_CHEQUE,
                now(),
                'cheque_claim',
            );

            $cheque->update([
                'status'             => SaleCheque::STATUS_CLEARED,
                'deposit_account_id' => $accountId,
                'claimed_by'         => $user->id,
                'claimed_at'         => now(),
                'notes'              => filled($notes) ? trim((string) $notes) : $cheque->notes,
            ]);

            $sale->update(['is_settled' => true, 'settled_at' => now()]);

            return $cheque->refresh();
        });
    }

    /** The bank returned the cheque unpaid. It can still be claimed later if re-presented. */
    public function bounce(SaleCheque $cheque, Business $business, string $reason): SaleCheque
    {
        if ((int) $cheque->business_id !== (int) $business->id) {
            abort(404);
        }

        return DB::transaction(function () use ($cheque, $reason): SaleCheque {
            $cheque = SaleCheque::query()->whereKey($cheque->id)->lockForUpdate()->firstOrFail();

            if (! $cheque->isPending()) {
                throw ValidationException::withMessages([
                    'cheque' => 'Only a pending cheque can be marked as bounced.',
                ]);
            }

            $cheque->update([
                'status'        => SaleCheque::STATUS_BOUNCED,
                'bounced_at'    => now(),
                'bounce_reason' => mb_substr(trim($reason), 0, 255),
            ]);

            return $cheque->refresh();
        });
    }

    /** @param  array{q?: string, status?: string, customer_id?: int|string|null, date_from?: string, date_to?: string}  $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $b) use ($like) {
                $b->where('cheque_number', 'like', $like)
                  ->orWhere('bank_name', 'like', $like)
                  ->orWhereHas('sale', fn ($q) => $q->where('sale_number', 'like', $like))
                  ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like));
            });
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status === 'due') {
            $query->where('status', SaleCheque::STATUS_PENDING)->whereDate('cheque_date', '<=', now()->toDateString());
        } elseif (array_key_exists($status, SaleCheque::STATUSES)) {
            $query->where('status', $status);
        }

        if (filled($filters['customer_id'] ?? null)) {
            $query->where('pos_customer_id', (int) $filters['customer_id']);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('cheque_date', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('cheque_date', '<=', $filters['date_to']);
        }
    }
}
