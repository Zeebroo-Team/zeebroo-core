<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Services\CustomerImportService;
use Modules\CRM\Services\CustomerService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Customer;
use Modules\Pos\Models\PosCashier;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleItem;
use Modules\Pos\Services\CustomerCreditService;
use Modules\Pos\Services\PosSettingsService;

class PosCustomerApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly CustomerCreditService $credit,
        private readonly CustomerImportService $imports,
        private readonly CustomerService $customers,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $q = (string) $request->query('q', '');
        $categoryId = $request->query('category_id');
        $customerType = (string) $request->query('customer_type', '');

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($categoryId !== null && $categoryId !== '', fn ($query) => $query->where('customer_category_id', (int) $categoryId))
            ->when(in_array($customerType, ['retail', 'wholesale'], true), fn ($query) => $query->where('customer_type', $customerType))
            ->withCount('sales')
            ->withCreditOutstanding()
            ->with('category:id,name')
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'data' => collect($customers->items())->map(fn (Customer $c) => $this->format($c))->values(),
            'meta' => [
                'total'        => $customers->total(),
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $customer->business_id !== (int) $business->id) abort(403);

        $customer->loadCount('sales');
        $customer->load([
            'sales' => fn ($q) => $q->latest('sold_at')->limit(5)->select('id', 'pos_customer_id', 'sale_number', 'total', 'sold_at', 'payment_method'),
            'category:id,name',
        ]);
        $customer->setAttribute('credit_outstanding', $this->credit->outstanding($customer));

        return response()->json(['data' => $this->format($customer, full: true)]);
    }

    public function creditPayment(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $customer->business_id === (int) $business->id, 404);
        $this->abortUnlessPerm($request, $business, 'pos_checkout');

        $validated = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'payment_method'    => ['nullable', 'string', 'in:cash,card'],
            'credit_account_id' => ['nullable', 'integer', 'min:1'],
            'pos_sale_id'       => ['nullable', 'integer', 'min:1'],
        ]);

        // PosCashier tokens are not User instances; book the payment under the business owner
        $authUser = $request->user();
        $user = $authUser instanceof PosCashier ? User::findOrFail($business->user_id) : $authUser;

        try {
            $result = $this->credit->receivePayment(
                $business,
                $customer,
                $user,
                (float) $validated['amount'],
                $validated['payment_method'] ?? Sale::PAYMENT_CASH,
                isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
                isset($validated['pos_sale_id']) ? (int) $validated['pos_sale_id'] : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not record the payment.',
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Payment of '.number_format($result['amount'], 2).' received.',
            'data'    => array_merge($result, $this->credit->summary($customer)),
        ]);
    }

    public function warranties(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $customer->business_id === (int) $business->id, 404);

        $today = now()->startOfDay();

        $items = SaleItem::query()
            ->whereNotNull('warranty_type')
            ->whereHas('sale', fn ($q) => $q->where('business_id', $business->id)->where('pos_customer_id', $customer->id))
            ->with('sale:id,sale_number,sold_at')
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $items->map(fn (SaleItem $i) => [
                'id'                  => $i->id,
                'product_name'        => $i->product_name,
                'sku'                 => $i->sku,
                'sale_number'         => $i->sale?->sale_number,
                'sold_at'             => $i->sale?->sold_at?->toDateString(),
                'warranty_type'       => $i->warranty_type,
                'warranty_days'       => $i->warranty_days,
                'warranty_expires_at' => $i->warranty_expires_at?->toDateString(),
                'is_expired'          => $i->warranty_type === 'days' && $i->warranty_expires_at !== null && $i->warranty_expires_at->lt($today),
            ])->values(),
        ]);
    }

    public function creditSales(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $customer->business_id === (int) $business->id, 404);

        $sales = Sale::query()
            ->where('business_id', $business->id)
            ->where('pos_customer_id', $customer->id)
            ->where('payment_method', Sale::PAYMENT_CREDIT)
            ->where('status', Sale::STATUS_COMPLETED)
            ->orderByDesc('sold_at')
            ->limit(100)
            ->get();

        $data = $sales->map(function (Sale $s) {
            $due       = $s->creditDue();
            $isOverdue = $s->isCreditOverdue();

            return [
                'id'              => $s->id,
                'sale_number'     => $s->sale_number,
                'total'           => round((float) $s->total, 2),
                'amount_paid'     => round((float) $s->amount_paid, 2),
                'due_amount'      => $due,
                'sold_at'         => $s->sold_at?->toDateString(),
                'credit_due_date' => $s->credit_due_date?->format('Y-m-d'),
                'is_overdue'      => $isOverdue,
                'is_paid'         => $due <= 0.01,
            ];
        })->values();

        return response()->json([
            'data'     => $data,
            'payments' => $this->credit->paymentHistory($customer),
            'meta'     => array_merge(
                ['total_due' => round($data->sum('due_amount'), 2)],
                $this->credit->summary($customer),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        $validated = $request->validate($this->rules($business));

        $customer = Customer::create(array_merge($validated, ['business_id' => $business->id]));
        $customer->loadCount('sales');
        $customer->load('category:id,name');

        try {
            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch('customer.created', $business, [
                'event'    => 'customer.created',
                'customer' => ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone, 'created_at' => $customer->created_at?->toIso8601String()],
            ]);
        } catch (\Throwable) {}

        return response()->json(['data' => $this->format($customer)], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $customer->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        $rules = $this->rules($business);
        $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];

        $validated = $request->validate($rules);

        $customer->update($validated);
        $customer->loadCount('sales');
        $customer->load('category:id,name');
        $customer->setAttribute('credit_outstanding', $this->credit->outstanding($customer));

        return response()->json(['data' => $this->format($customer)]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $customer->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    /**
     * Older desktop builds send only `rows` and read imported / skipped / errors back; they now get
     * the duplicate check, with duplicates skipped and listed in errors. Newer builds add
     * `duplicates` (skip|update) and also read updated / totals / rows.
     */
    public function import(Request $request): JsonResponse
    {
        return $this->runImport($request, preview: false);
    }

    /** Same request and response as import(), but only checks the rows — nothing is saved. */
    public function importPreview(Request $request): JsonResponse
    {
        return $this->runImport($request, preview: true);
    }

    private function runImport(Request $request, bool $preview): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        $request->validate([
            'rows'       => ['required', 'array', 'min:1', 'max:500'],
            'rows.*'     => ['array'],
            'duplicates' => ['nullable', 'string', Rule::in(array_keys(CustomerImportService::DUPLICATE_MODES))],
        ]);

        // Rows are numbered from 1 in the order sent, as before
        $rows = [];
        foreach (array_values($request->input('rows')) as $idx => $row) {
            $rows[] = ['row' => $idx + 1] + $row;
        }
        $mode = $request->input('duplicates') ?: 'skip';

        $result = $preview
            ? $this->imports->preview($business, $rows, $mode)
            : $this->imports->import($business, $rows, $mode);

        $notImported = array_values(array_filter($result['rows'], fn ($r) => in_array($r['status'], ['skip', 'error'], true)));

        return response()->json([
            'imported'   => $preview ? 0 : $result['totals']['create'],
            'updated'    => $preview ? 0 : $result['totals']['update'],
            'skipped'    => $result['totals']['skip'] + $result['totals']['error'],
            'errors'     => array_map(fn ($r) => [
                'row'     => $r['row'],
                'name'    => $r['name'] ?? '',
                'message' => $r['reason'],
                'type'    => $r['status'] === 'skip' ? 'duplicate' : 'error',
            ], $notImported),
            'preview'    => $preview,
            'duplicates' => $mode,
            'totals'     => $result['totals'],
            'rows'       => $result['rows'],
        ]);
    }

    /** Customers sharing a phone (normalised) or email with the form, so the desktop can warn before saving. */
    public function duplicates(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'phone'     => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'string', 'max:255'],
            'ignore_id' => ['nullable', 'integer'],
        ]);

        $duplicates = $this->customers->presentDuplicates(
            $business->id,
            $validated['phone'] ?? null,
            $validated['email'] ?? null,
            isset($validated['ignore_id']) ? (int) $validated['ignore_id'] : null,
        );

        return response()->json([
            'data'    => $duplicates,
            'message' => $this->customers->duplicateWarning($duplicates),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(Business $business): array
    {
        $settings = app(PosSettingsService::class)->forBusiness($business);

        return [
            'name'                 => ['required', 'string', 'max:255'],
            'phone'                => [!empty($settings['customer_require_phone']) ? 'required' : 'nullable', 'string', 'max:50'],
            'email'                => [!empty($settings['customer_require_email']) ? 'required' : 'nullable', 'email', 'max:255'],
            'address'              => [!empty($settings['customer_require_address']) ? 'required' : 'nullable', 'string', 'max:500'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'customer_type'        => ['nullable', 'string', 'in:retail,wholesale'],
            'credit_limit'         => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'customer_category_id' => [
                'nullable', 'integer',
                Rule::exists('pos_customer_categories', 'id')->where('business_id', $business->id),
            ],
        ];
    }

    private function format(Customer $c, bool $full = false): array
    {
        $data = [
            'id'                   => $c->id,
            'name'                 => $c->name,
            'phone'                => $c->phone,
            'email'                => $c->email,
            'address'              => $c->address,
            'notes'                => $c->notes,
            'customer_type'        => $c->customer_type ?? 'retail',
            'customer_category_id' => $c->customer_category_id,
            'category_name'        => $c->relationLoaded('category') ? $c->category?->name : null,
            'sales_count'          => $c->sales_count ?? 0,
        ];

        // credit_limit / credit_outstanding / credit_available
        $data += $this->credit->summary($c, (float) ($c->credit_outstanding ?? 0));

        if ($full && $c->relationLoaded('sales')) {
            $data['recent_sales'] = $c->sales->map(fn ($s) => [
                'id'             => $s->id,
                'sale_number'    => $s->sale_number,
                'total'          => $s->total,
                'payment_method' => $s->payment_method,
                'sold_at'        => $s->sold_at?->toDateTimeString(),
            ])->values();
        }

        return $data;
    }
}
