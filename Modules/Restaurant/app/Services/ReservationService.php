<?php

namespace Modules\Restaurant\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Business\Models\Business;
use Modules\Restaurant\Models\Reservation;

class ReservationService
{
    public function listForBusiness(Business $business, string $status = 'all', string $date = ''): LengthAwarePaginator
    {
        $query = Reservation::where('business_id', $business->id)->with('table');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($date !== '') {
            $query->whereDate('reserved_at', $date);
        }

        return $query->orderBy('reserved_at')->paginate(30);
    }

    public function create(Business $business, array $data): Reservation
    {
        return Reservation::create(['business_id' => $business->id] + $data);
    }

    public function update(Reservation $reservation, array $data): void
    {
        $reservation->update($data);
    }

    public function changeStatus(Reservation $reservation, string $status): void
    {
        $reservation->update(['status' => $status]);
    }

    public function delete(Reservation $reservation): void
    {
        $reservation->delete();
    }
}
