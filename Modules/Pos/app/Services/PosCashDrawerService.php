<?php

namespace Modules\Pos\Services;

use Modules\Business\Models\Business;
use Modules\Pos\Models\PosCashOpening;
use Modules\Pos\Models\PosCashWithdrawal;
use Modules\Pos\Models\Sale;

class PosCashDrawerService
{
    public function todayStatus(Business $business, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();

        $opening = PosCashOpening::where('business_id', $business->id)
            ->whereDate('register_date', $date)
            ->first();

        $cashSales = (float) Sale::where('business_id', $business->id)
            ->where('payment_method', Sale::PAYMENT_CASH)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('sold_at', $date)
            ->sum('total');

        $withdrawals = PosCashWithdrawal::where('business_id', $business->id)
            ->whereDate('register_date', $date)
            ->orderBy('created_at')
            ->get();

        $totalWithdrawals = (float) $withdrawals->sum('amount');
        $openingFloat     = $opening ? (float) $opening->opening_float : null;

        $balance = $openingFloat !== null
            ? round($openingFloat + $cashSales - $totalWithdrawals, 2)
            : null;

        return [
            'date'              => $date,
            'is_opened'         => $opening !== null,
            'opening_float'     => $openingFloat,
            'cash_sales'        => round($cashSales, 2),
            'total_withdrawals' => round($totalWithdrawals, 2),
            'balance'           => $balance,
            'withdrawals'       => $withdrawals->map(fn ($w) => [
                'id'     => $w->id,
                'amount' => (float) $w->amount,
                'note'   => $w->note,
                'time'   => $w->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function setOpening(Business $business, float $amount, ?int $userId = null): PosCashOpening
    {
        $existing = PosCashOpening::where('business_id', $business->id)
            ->whereDate('register_date', now()->toDateString())
            ->first();

        if ($existing) {
            $existing->update(['opening_float' => max(0, $amount), 'user_id' => $userId]);
            return $existing->fresh();
        }

        return PosCashOpening::create([
            'business_id'   => $business->id,
            'register_date' => now()->toDateString(),
            'opening_float' => max(0, $amount),
            'user_id'       => $userId,
        ]);
    }

    public function addWithdrawal(Business $business, float $amount, ?string $note, ?int $userId = null): PosCashWithdrawal
    {
        return PosCashWithdrawal::create([
            'business_id'   => $business->id,
            'register_date' => now()->toDateString(),
            'amount'        => max(0, $amount),
            'note'          => $note ? trim(substr($note, 0, 255)) : null,
            'user_id'       => $userId,
        ]);
    }
}
