<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosBillScanService;
use RuntimeException;

class PosExpenseBillScanApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __invoke(Request $request, PosBillScanService $scanner): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_bills');
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=32,min_height=32,max_width=10000,max_height=10000'],
        ]);
        if (trim((string) config('services.gemini.key', '')) === '') {
            return response()->json(['message' => 'Bill scanning is not configured. Add GEMINI_API_KEY on the server.'], 503);
        }
        try {
            $draft = $scanner->scan($request->file('image'));
        } catch (ConnectionException) {
            return response()->json(['message' => 'Bill scanning timed out or could not reach Gemini. Please try again.'], 504);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Gemini could not read this bill. Try again with a clear image, or enter the bill manually.'], 502);
        }

        return response()->json(['data' => $draft, 'message' => 'Review every field before saving. Missing or invalid fields were left empty.']);
    }
}
