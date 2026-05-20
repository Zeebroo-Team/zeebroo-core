<?php

namespace Modules\AIBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;

class AIBotController extends Controller
{
    public function index(Request $request): View
    {
        $business = Business::currentForNavbar($request->user());
        $businessLabel = $business?->name ?? 'Your workspace';

        return view('aibot::index', [
            'business' => $business,
            'businessLabel' => $businessLabel,
        ]);
    }
}
