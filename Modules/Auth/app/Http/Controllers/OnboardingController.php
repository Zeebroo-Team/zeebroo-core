<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\BusinessCategory;

class OnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->businesses()->exists()) {
            return redirect()->route('dashboard');
        }

        return view('auth::auth.onboarding', [
            'user'            => $user,
            'categoryOptions' => BusinessCategory::optionsForSelect(),
        ]);
    }
}
