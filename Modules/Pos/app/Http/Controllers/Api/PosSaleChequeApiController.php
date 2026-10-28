<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\PosCashier;
use Modules\Pos\Models\SaleCheque;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\SaleChequeService;

class PosSaleChequeApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly SaleChequeService $cheques,
        private readonly PosOnlineApiService $api,
    ) {
    }

    /** Sales → Cheques tab (and Customers → Cheques with customer_id): paginated list plus totals. */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $filters = [
            'q'           => (string) $request->query('q', ''),
            'status'      => (string) $request->query('status', 'all'),
            'customer_id' => $request->query('customer_id'),
            'date_from'   => (string) $request->query('date_from', ''),
            'date_to'     => (string) $request->query('date_to', ''),
        ];
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $paginator = $this->cheques->indexForBusiness($business, $filters, $perPage);

        return response()->json([
            'data'     => $paginator->getCollection()->map(fn (SaleCheque $c) => $this->api->formatSaleCheque($c))->values()->all(),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
            'summary'  => $this->cheques->summaryForBusiness($business, $filters),
            'statuses' => SaleCheque::STATUSES,
        ]);
    }

    public function show(Request $request, SaleCheque $saleCheque): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $saleCheque->business_id === (int) $business->id, 404);

        return response()->json(['data' => $this->api->formatSaleCheque($saleCheque)]);
    }

    /** Claim (deposit) the cheque into an account. */
    public function claim(Request $request, SaleCheque $saleCheque): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $saleCheque->business_id === (int) $business->id, 404);
        $this->abortUnlessPerm($request, $business, 'pos_checkout');

        $validated = $request->validate([
            'deposit_account_id' => ['nullable', 'integer', 'min:1'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $cheque = $this->cheques->claim(
                $saleCheque,
                $business,
                $this->actingUser($request, $business),
                isset($validated['deposit_account_id']) ? (int) $validated['deposit_account_id'] : null,
                $validated['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Could not claim this cheque.',
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cheque '.$cheque->cheque_number.' claimed.',
            'data'    => $this->api->formatSaleCheque($cheque),
        ]);
    }

    /** The bank returned the cheque unpaid. */
    public function bounce(Request $request, SaleCheque $saleCheque): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $saleCheque->business_id === (int) $business->id, 404);
        $this->abortUnlessPerm($request, $business, 'pos_checkout');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $cheque = $this->cheques->bounce($saleCheque, $business, $validated['reason']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Could not update this cheque.',
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cheque '.$cheque->cheque_number.' marked as bounced.',
            'data'    => $this->api->formatSaleCheque($cheque),
        ]);
    }

    /** PosCashier tokens are not User instances; act as the business owner, like checkout does. */
    private function actingUser(Request $request, Business $business): User
    {
        $user = $request->user() ?? abort(401);

        return $user instanceof PosCashier ? User::findOrFail($business->user_id) : $user;
    }
}
