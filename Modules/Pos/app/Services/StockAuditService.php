<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Pos\Models\StockAudit;
use Modules\Pos\Models\StockAuditLine;
use Modules\Product\Models\Product;

class StockAuditService
{
    private const PER_PAGE = 25;

    // ── Queries ────────────────────────────────────────────────────

    public function listForBusiness(Business $business): LengthAwarePaginator
    {
        return StockAudit::query()
            ->where('business_id', $business->id)
            ->withCount('lines')
            ->orderByDesc('audit_date')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    public function businessHasAudits(Business $business): bool
    {
        return StockAudit::query()->where('business_id', $business->id)->exists();
    }

    public function auditForBusiness(Business $business, StockAudit $audit): StockAudit
    {
        abort_unless((int) $audit->business_id === (int) $business->id, 404);

        return $audit;
    }

    public function productsForPreview(Business $business): int
    {
        return Product::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->where('is_bundle', false)
            ->count();
    }

    // ── Mutations ──────────────────────────────────────────────────

    public function create(Business $business, array $data, User $user): StockAudit
    {
        return DB::transaction(function () use ($business, $data, $user): StockAudit {
            $audit = StockAudit::create([
                'business_id'  => $business->id,
                'audit_number' => $this->nextAuditNumber($business),
                'audit_date'   => $data['audit_date'],
                'status'       => StockAudit::STATUS_OPEN,
                'notes'        => $data['notes'] ?? null,
                'created_by'   => $user->id,
            ]);

            // Snapshot all active, non-bundle products
            $products = Product::query()
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->where('is_bundle', false)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit', 'stock_quantity']);

            $lines = $products->values()->map(fn ($p, $i) => [
                'stock_audit_id' => $audit->id,
                'product_id'     => $p->id,
                'product_name'   => $p->name,
                'sku'            => $p->sku,
                'unit'           => $p->unit,
                'expected_qty'   => (float) $p->stock_quantity,
                'counted_qty'    => null,
                'notes'          => null,
                'sort_order'     => $i,
                'created_at'     => now(),
                'updated_at'     => now(),
            ])->all();

            if (!empty($lines)) {
                StockAuditLine::insert($lines);
            }

            return $audit;
        });
    }

    public function saveLines(StockAudit $audit, array $lines): void
    {
        abort_if($audit->isFinalized(), 422, 'Cannot edit a finalized audit.');

        DB::transaction(function () use ($audit, $lines): void {
            foreach ($lines as $lineId => $data) {
                $countedRaw = $data['counted_qty'] ?? null;
                $counted    = ($countedRaw !== null && $countedRaw !== '')
                    ? max(0, (float) $countedRaw)
                    : null;

                StockAuditLine::where('stock_audit_id', $audit->id)
                    ->where('id', (int) $lineId)
                    ->update([
                        'counted_qty' => $counted,
                        'notes'       => isset($data['notes']) ? substr((string) $data['notes'], 0, 500) : null,
                        'updated_at'  => now(),
                    ]);
            }
        });
    }

    public function finalize(StockAudit $audit, User $user): void
    {
        abort_if($audit->isFinalized(), 422, 'Audit is already finalized.');

        DB::transaction(function () use ($audit, $user): void {
            // Apply counted quantities to product stock
            $lines = StockAuditLine::query()
                ->where('stock_audit_id', $audit->id)
                ->whereNotNull('counted_qty')
                ->whereNotNull('product_id')
                ->get();

            foreach ($lines as $line) {
                Product::where('id', $line->product_id)
                    ->update(['stock_quantity' => (float) $line->counted_qty]);
            }

            $audit->update([
                'status'       => StockAudit::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by' => $user->id,
            ]);
        });
    }

    public function delete(StockAudit $audit): void
    {
        abort_if($audit->isFinalized(), 422, 'Cannot delete a finalized audit.');
        $audit->delete();
    }

    // ── Private helpers ────────────────────────────────────────────

    private function nextAuditNumber(Business $business): string
    {
        $last = StockAudit::query()
            ->where('business_id', $business->id)
            ->orderByDesc('id')
            ->value('audit_number');

        if ($last && preg_match('/AUD-(\d+)$/i', $last, $m)) {
            return 'AUD-' . str_pad((int) $m[1] + 1, 4, '0', STR_PAD_LEFT);
        }

        return 'AUD-0001';
    }
}
