<?php

namespace Modules\Account\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Rental;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class RentalExternalPaymentMarkService
{
    public function __construct(
        private readonly RentalService $rentalSchedule,
    ) {}

    /**
     * Record that this scheduled billing date was paid outside SociBiz (no ledger row, no account debit).
     */
    public function mark(Rental $rental, Business $business, User $user, string $occurrenceDateYmd): void
    {
        if ($rental->user_id !== $user->id || (int) $rental->business_id !== (int) $business->id) {
            abort(403);
        }

        $rental->loadMissing(['business']);

        try {
            $occurrence = Carbon::parse($occurrenceDateYmd)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Invalid billing date.',
            ]);
        }

        $schedule = $this->rentalSchedule->rentalScheduledBillingDates($rental);
        $onSchedule = false;
        foreach ($schedule as $dueDate) {
            if ($dueDate->copy()->startOfDay()->toDateString() === $occurrence->toDateString()) {
                $onSchedule = true;
                break;
            }
        }
        if (! $onSchedule || $schedule->isEmpty()) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'That date is not on this rental’s billing schedule.',
            ]);
        }

        DB::transaction(function () use ($rental, $occurrence): void {
            $ledgerExists = LedgerTransaction::query()
                ->where('transactionable_type', Rental::class)
                ->where('transactionable_id', $rental->getKey())
                ->whereDate('occurrence_date', $occurrence->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($ledgerExists) {
                throw ValidationException::withMessages([
                    'occurrence_date' => 'This billing date already has a ledger payment.',
                ]);
            }

            $rental->externalBillingMarks()->firstOrCreate(
                ['due_date' => $occurrence->toDateString()],
                [],
            );
        });
    }
}
