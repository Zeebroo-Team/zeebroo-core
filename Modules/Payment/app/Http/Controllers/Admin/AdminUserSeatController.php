<?php

namespace Modules\Payment\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Package\Models\Package;
use Modules\Payment\Services\AdminUserSeatOverviewService;

/** Admin → User Seats & Extra Users: seat usage per business and who holds each seat. */
class AdminUserSeatController extends Controller
{
    public function __construct(private readonly AdminUserSeatOverviewService $seats) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'seats', 'package', 'sort']);

        return view('payment::admin.seats.index', [
            'overview' => $this->seats->overview(0),
            'businesses' => $this->seats->paginate($filters, 25),
            'packages' => Package::query()->orderBy('name')->get(['id', 'name', 'max_users']),
        ]);
    }

    public function show(Business $business): View
    {
        return view('payment::admin.seats.show', [
            'business' => $business,
            'detail' => $this->seats->detail($business),
        ]);
    }
}
