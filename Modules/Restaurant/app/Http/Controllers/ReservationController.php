<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Restaurant\Http\Controllers\Concerns\ResolvesRestaurantBusiness;
use Modules\Restaurant\Models\Reservation;
use Modules\Restaurant\Models\RestaurantTable;
use Modules\Restaurant\Services\ReservationService;

class ReservationController extends Controller
{
    use ResolvesRestaurantBusiness;

    public function __construct(private readonly ReservationService $reservations) {}

    public function index(Request $request): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $status = (string) $request->query('status', 'all');
        $date   = (string) $request->query('date', '');

        $todayDate    = now()->toDateString();
        $statusCounts = Reservation::where('business_id', $business->id)
            ->whereDate('reserved_at', $todayDate)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('restaurant::reservations.index', [
            'business'     => $business,
            'reservations' => $this->reservations->listForBusiness($business, $status, $date),
            'tables'       => RestaurantTable::where('business_id', $business->id)->where('status', '!=', 'inactive')->orderBy('name')->get(),
            'status'       => $status,
            'date'         => $date,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_phone'   => ['nullable', 'string', 'max:30'],
            'customer_email'   => ['nullable', 'email', 'max:255'],
            'party_size'       => ['required', 'integer', 'min:1', 'max:500'],
            'table_id'         => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'reserved_at'      => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        $data['status']           = 'pending';
        $data['duration_minutes'] = $data['duration_minutes'] ?? 90;

        $this->reservations->create($business, $data);

        return redirect()->route('restaurant.reservations.index')->with('status', 'Reservation created.');
    }

    public function edit(Request $request, Reservation $reservation): \Illuminate\View\View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $reservation->business_id === (int) $business->id, 404);

        return view('restaurant::reservations.edit', [
            'business'    => $business,
            'reservation' => $reservation->load('table'),
            'tables'      => RestaurantTable::where('business_id', $business->id)->where('status', '!=', 'inactive')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $reservation->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_phone'   => ['nullable', 'string', 'max:30'],
            'customer_email'   => ['nullable', 'email', 'max:255'],
            'party_size'       => ['required', 'integer', 'min:1', 'max:500'],
            'table_id'         => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'reserved_at'      => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'status'           => ['required', 'in:pending,confirmed,seated,completed,cancelled'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        $this->reservations->update($reservation, $data);

        return redirect()->route('restaurant.reservations.index')->with('status', 'Reservation updated.');
    }

    public function quickStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $reservation->business_id === (int) $business->id, 404);

        $newStatus = $request->validate(['status' => ['required', 'in:pending,confirmed,seated,completed,cancelled']])['status'];
        $this->reservations->changeStatus($reservation, $newStatus);

        return back()->with('status', 'Reservation marked as ' . ucfirst($newStatus) . '.');
    }

    public function destroy(Request $request, Reservation $reservation): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $reservation->business_id === (int) $business->id, 404);

        $this->reservations->delete($reservation);

        return redirect()->route('restaurant.reservations.index')->with('status', 'Reservation deleted.');
    }
}
