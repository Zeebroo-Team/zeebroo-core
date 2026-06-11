<?php

namespace Modules\DesignStudio\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\DesignStudio\Models\Design;

class DesignEditorController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $hub = new DesignStudioController();
        $business = $hub->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('designstudio::editor.index', [
            'business' => $business,
            'design'   => null,
        ]);
    }

    public function edit(Request $request, Design $design): View|RedirectResponse
    {
        $hub = new DesignStudioController();
        $business = $hub->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ((int) $design->business_id !== (int) $business->id) {
            abort(403);
        }

        return view('designstudio::editor.index', [
            'business' => $business,
            'design'   => $design,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $hub = new DesignStudioController();
        $business = $hub->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'type'        => ['nullable', 'string', 'max:50'],
            'width'       => ['required', 'integer', 'min:100', 'max:8000'],
            'height'      => ['required', 'integer', 'min:100', 'max:8000'],
            'canvas_json' => ['nullable', 'string'],
        ]);

        $design = Design::create([
            'business_id' => $business->id,
            'user_id'     => $request->user()->id,
            ...$validated,
        ]);

        return response()->json(['id' => $design->id, 'message' => 'Design saved']);
    }

    public function update(Request $request, Design $design): JsonResponse
    {
        $hub = new DesignStudioController();
        $business = $hub->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ((int) $design->business_id !== (int) $business->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'type'        => ['nullable', 'string', 'max:50'],
            'width'       => ['required', 'integer', 'min:100', 'max:8000'],
            'height'      => ['required', 'integer', 'min:100', 'max:8000'],
            'canvas_json' => ['nullable', 'string'],
        ]);

        $design->update($validated);

        return response()->json(['id' => $design->id, 'message' => 'Design saved']);
    }

    public function destroy(Request $request, Design $design): RedirectResponse
    {
        $hub = new DesignStudioController();
        $business = $hub->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ((int) $design->business_id !== (int) $business->id) {
            abort(403);
        }

        $design->delete();

        return redirect()->route('designstudio.index')->with('status', 'Design deleted.');
    }
}
