<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ── Public pages (no auth required) ──────────────────────────────────────────
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('terms-of-service');
})->name('terms-of-service');

Route::get('/', function () {
    if (Auth::check()) {
        return app(DashboardController::class)->dashboard(request());
    }

    return view('auth::auth.login');
})->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/admin', [DashboardController::class, 'adminPanel'])->middleware('role:admin')->name('admin.panel');
    Route::post('/business/select', function (\Illuminate\Http\Request $request) {
        $id       = (int) $request->input('business_id');
        $business = \Modules\Business\Models\Business::find($id);
        if ($business && \Modules\Business\Models\Business::canAccess($request->user(), $business)) {
            session(['selected_business_id' => $id]);
            session()->forget('selected_account_id');
        }

        return redirect()->back();
    })->name('business.select');

    Route::post('/account/select', function (\Illuminate\Http\Request $request) {
        $business = \Modules\Business\Models\Business::currentForNavbar($request->user());

        if (!$business) {
            return redirect()->back();
        }

        $accountId = (int) $request->input('account_id');
        $isValid = \Modules\Account\Models\Account::query()
            ->where('id', $accountId)
            ->where('user_id', $request->user()->id)
            ->where('business_id', $business->id)
            ->exists();

        if ($isValid) {
            session(['selected_account_id' => $accountId]);
        } else {
            session()->forget('selected_account_id');
        }

        return redirect()->back();
    })->name('account.select');
});
