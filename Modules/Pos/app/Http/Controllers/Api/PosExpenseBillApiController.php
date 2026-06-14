<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Bill;
use Modules\Account\Services\BillService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosExpenseBillApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly BillService $service) {}

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        $request->merge([
            'branch_id'                  => $request->filled('branch_id')       ? $request->integer('branch_id')       : null,
            'department_id'              => $request->filled('department_id')    ? $request->integer('department_id')    : null,
            'property_id'                => $request->filled('property_id')      ? $request->integer('property_id')      : null,
            'employee_id'                => $request->filled('employee_id')      ? $request->integer('employee_id')      : null,
            'modification_id'            => $request->filled('modification_id')  ? $request->integer('modification_id')  : null,
            'deduct_account_id'          => $request->filled('deduct_account_id')? $request->integer('deduct_account_id'): null,
            'remind_before_days'         => $request->filled('remind_before_days')? $request->integer('remind_before_days'): null,
            'due_date'                   => $request->filled('due_date')         ? $request->input('due_date')           : null,
            'first_installment_due_date' => $request->filled('first_installment_due_date') ? $request->input('first_installment_due_date') : null,
            'bill_category_other'        => $request->filled('bill_category_other') ? trim((string) $request->input('bill_category_other')) : null,
            'rental_property_related'    => $request->boolean('rental_property_related'),
            'rental_id'                  => $request->boolean('rental_property_related') && $request->filled('rental_id') ? $request->integer('rental_id') : null,
            'assignment_type'            => $request->filled('assignment_type') ? (string) $request->input('assignment_type') : 'none',
        ]);

        $deptRules = ['nullable', 'integer'];
        if (Schema::hasTable('hr_departments')) {
            $deptRules[] = Rule::exists('hr_departments', 'id')->where(fn ($q) => $q->where('business_id', $business->id));
        }

        $validated = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'payment_mode'               => ['required', Rule::in([Bill::PAYMENT_MODE_RECURRING, Bill::PAYMENT_MODE_ONE_TIME])],
            'bill_category'              => ['required', Rule::in(array_keys(Bill::billCategories()))],
            'bill_category_other'        => ['nullable', 'string', 'max:255'],
            'description'                => ['nullable', 'string', 'max:2000'],
            'agreement_valid_until_year' => [
                Rule::requiredIf(fn () => $request->input('payment_mode') === Bill::PAYMENT_MODE_RECURRING),
                'nullable', 'integer', 'min:2000', 'max:2100',
            ],
            'branch_id'       => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'department_id'   => $deptRules,
            'property_id'     => ['nullable', 'integer', Rule::exists('properties', 'id')->where(fn ($q) => $q->where('business_id', $business->id)->where('user_id', $user->id))],
            'employee_id'     => ['nullable', 'integer', Rule::exists('hr_employees', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'modification_id' => ['nullable', 'integer', Rule::exists('modifications', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'rental_property_related' => ['boolean'],
            'rental_id'       => [
                Rule::requiredIf(fn () => $request->boolean('rental_property_related')),
                'nullable', 'integer',
                Rule::exists('rentals', 'id')->where(fn ($q) => $q->where('business_id', $business->id)->where('user_id', $user->id)),
            ],
            'assignment_type'            => ['nullable', Rule::in(['none', 'branch', 'department', 'property', 'employee', 'modification', 'rental'])],
            'deduct_account_id'          => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('user_id', $user->id)->where('business_id', $business->id))],
            'amount_varies_by_usage'     => ['sometimes', 'boolean'],
            'allow_split_payment'        => ['sometimes', 'boolean'],
            'recurring_cost'             => [Rule::requiredIf(fn () => ! $request->boolean('amount_varies_by_usage')), 'nullable', 'numeric', 'min:0'],
            'recurring_type'             => [Rule::requiredIf(fn () => $request->input('payment_mode') === Bill::PAYMENT_MODE_RECURRING), 'nullable', Rule::in([Bill::RECURRING_PER_DAY, Bill::RECURRING_PER_MONTH, Bill::RECURRING_PER_YEAR])],
            'notes'                      => ['nullable', 'string', 'max:5000'],
            'remind_before_days'         => ['nullable', 'integer', 'min:0', 'max:366'],
            'due_date'                   => ['nullable', 'date'],
            'first_installment_due_date' => ['nullable', 'date'],
        ]);

        // Enforce assignment mutual exclusivity
        $assignmentType = (string) ($validated['assignment_type'] ?? 'none');
        if ($assignmentType !== 'branch')       $validated['branch_id']       = null;
        if ($assignmentType !== 'department')   $validated['department_id']   = null;
        if ($assignmentType !== 'property')     $validated['property_id']     = null;
        if ($assignmentType !== 'employee')     $validated['employee_id']     = null;
        if ($assignmentType !== 'modification') $validated['modification_id'] = null;
        if ($assignmentType === 'rental') {
            $validated['rental_property_related'] = true;
        } else {
            $validated['rental_property_related'] = false;
            $validated['rental_id']               = null;
        }

        $validated['amount_varies_by_usage'] = (bool) ($validated['amount_varies_by_usage'] ?? false);
        $validated['allow_split_payment']    = (bool) ($validated['allow_split_payment']    ?? true);

        // bill_category_other required when category = other
        if (($validated['bill_category'] ?? '') === Bill::CATEGORY_OTHER) {
            if (trim((string) ($validated['bill_category_other'] ?? '')) === '') {
                throw ValidationException::withMessages(['bill_category_other' => 'Describe this bill when you choose Other.']);
            }
        } else {
            $validated['bill_category_other'] = null;
        }

        // One-time: require a date anchor and auto-compute agreement_valid_until_year
        if (($validated['payment_mode'] ?? '') === Bill::PAYMENT_MODE_ONE_TIME) {
            if (empty($validated['due_date']) && empty($validated['first_installment_due_date'])) {
                throw ValidationException::withMessages(['due_date' => 'Set a due date for this one-time bill (or use first installment date).']);
            }
            $anchor = $validated['due_date'] ?? $validated['first_installment_due_date'];
            $validated['agreement_valid_until_year'] = (int) Carbon::parse((string) $anchor)->format('Y');
            $validated['recurring_type'] = $validated['recurring_type'] ?? Bill::RECURRING_PER_MONTH;
        }

        $validated['recurring_cost'] = round((float) ($validated['recurring_cost'] ?? 0), 2);

        $bill = $this->service->create($user, $business, $validated);

        return response()->json(['message' => 'Bill created successfully.', 'data' => $this->format($bill)], 201);
    }

    private function format(Bill $bill): array
    {
        return [
            'id'                         => $bill->id,
            'name'                       => $bill->name,
            'bill_category'              => $bill->bill_category,
            'bill_category_other'        => $bill->bill_category_other,
            'payment_mode'               => $bill->payment_mode,
            'recurring_cost'             => (float) $bill->recurring_cost,
            'recurring_type'             => $bill->recurring_type,
            'agreement_valid_until_year' => $bill->agreement_valid_until_year,
            'first_installment_due_date' => $bill->first_installment_due_date?->format('Y-m-d'),
            'due_date'                   => $bill->due_date?->format('Y-m-d'),
            'remind_before_days'         => $bill->remind_before_days,
            'amount_varies_by_usage'     => (bool) $bill->amount_varies_by_usage,
            'allow_split_payment'        => (bool) $bill->allow_split_payment,
            'assignment_type'            => $bill->assignment_type ?? 'none',
            'branch_id'                  => $bill->branch_id,
            'department_id'              => $bill->department_id,
            'property_id'                => $bill->property_id,
            'employee_id'                => $bill->employee_id,
            'modification_id'            => $bill->modification_id,
            'rental_property_related'    => (bool) $bill->rental_property_related,
            'rental_id'                  => $bill->rental_id,
            'deduct_account_id'          => $bill->deduct_account_id,
            'description'                => $bill->description,
            'notes'                      => $bill->notes,
        ];
    }
}
