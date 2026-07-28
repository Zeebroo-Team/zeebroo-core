<?php

namespace Modules\Account\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Account\Models\Loan;
use Modules\Business\Models\Business;

class LoanService
{
    public function listForBusiness(?Business $business): Collection
    {
        if (! $business) {
            return new Collection([]);
        }

        return Loan::with([
            'bank',
            'deductAccount.bank',
            'deductAccount.bankType',
            'ledgerTransactions.deductAccount.bank',
            'ledgerTransactions.deductAccount.bankType',
        ])
            ->where('business_id', $business->id)
            ->latest()
            ->get();
    }

    public function create(User $user, Business $business, array $data): Loan
    {
        $data['user_id'] = $user->id;
        $data['business_id'] = $business->id;

        $loan = Loan::create($data);

        try {
            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch('loan.created', $business, [
                'event' => 'loan.created',
                'loan'  => [
                    'id'               => $loan->id,
                    'name'             => $loan->name,
                    'borrowed_amount'  => (float) $loan->borrowed_amount,
                    'interest_rate'    => (float) $loan->interest_rate,
                    'recurring_type'   => $loan->recurring_type,
                    'created_at'       => $loan->created_at?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable) {}

        return $loan;
    }

    /** Load loan with relations only if owned by user (scoped to businesses they belong to). */
    public function loanForUser(User $user, Loan $loan): ?Loan
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ($loan->user_id !== $user->id || ! in_array($loan->business_id, $businessIds, true)) {
            return null;
        }

        return Loan::query()
            ->whereKey($loan->getKey())
            ->with([
                'bank',
                'deductAccount.bank',
                'deductAccount.bankType',
                'ledgerTransactions.deductAccount.bank',
                'ledgerTransactions.deductAccount.bankType',
            ])
            ->first();
    }

    public function deleteForUser(User $user, Loan $loan): bool
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ($loan->user_id !== $user->id || ! in_array($loan->business_id, $businessIds, true)) {
            return false;
        }

        $loan->delete();

        return true;
    }
}
