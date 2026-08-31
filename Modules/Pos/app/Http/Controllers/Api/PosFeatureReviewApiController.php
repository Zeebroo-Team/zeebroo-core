<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\PosCashier;
use Modules\Pos\Models\PosFeatureReview;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PosFeatureReviewApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function summary(Request $request): JsonResponse
    {
        $this->businessOrAbort($request);

        $rows = PosFeatureReview::selectRaw('feature_key, AVG(rating) as average, COUNT(*) as count')
            ->groupBy('feature_key')
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $data[$row->feature_key] = [
                'average' => round((float) $row->average, 1),
                'count'   => (int) $row->count,
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function index(Request $request, string $key): JsonResponse
    {
        $this->businessOrAbort($request);
        $this->validateKey($key);

        $reviewerKey = $this->reviewerKey($request);
        $reviews     = PosFeatureReview::where('feature_key', $key)->latest()->get();

        $myReview = $reviews->firstWhere('reviewer_key', $reviewerKey);

        return response()->json([
            'data' => [
                'reviews'   => $reviews->map(fn (PosFeatureReview $r) => $this->present($r))->values(),
                'average'   => round((float) $reviews->avg('rating'), 1),
                'count'     => $reviews->count(),
                'my_review' => $myReview ? $this->present($myReview) : null,
            ],
        ]);
    }

    public function store(Request $request, string $key): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->validateKey($key);

        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $review = PosFeatureReview::updateOrCreate(
            ['feature_key' => $key, 'reviewer_key' => $this->reviewerKey($request)],
            [
                'business_id'   => $business->id,
                'reviewer_name' => $user->name ?? 'Anonymous',
                'rating'        => $validated['rating'],
                'comment'       => $validated['comment'] ?? null,
            ],
        );

        $all = PosFeatureReview::where('feature_key', $key)->get();

        return response()->json([
            'message' => 'Review saved.',
            'data' => [
                'review'  => $this->present($review),
                'average' => round((float) $all->avg('rating'), 1),
                'count'   => $all->count(),
            ],
        ]);
    }

    private function validateKey(string $key): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $key)) {
            throw new NotFoundHttpException();
        }
    }

    private function reviewerKey(Request $request): string
    {
        $user = $request->user();

        return ($user instanceof PosCashier ? 'cashier:' : 'user:') . $user->id;
    }

    private function present(PosFeatureReview $review): array
    {
        return [
            'id'         => $review->id,
            'name'       => $review->reviewer_name,
            'rating'     => $review->rating,
            'comment'    => $review->comment,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }
}
