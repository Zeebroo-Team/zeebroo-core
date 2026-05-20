<?php

namespace Modules\AIBot\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Account\Models\Account;
use Modules\Account\Models\Bill;
use Modules\Account\Services\BillService;
use Modules\Account\Services\LoanOverviewTooltipService;
use Modules\Account\Services\LoanService;
use Modules\Account\Services\RentalService;
use Modules\Business\Models\Business;
use Modules\HRManagement\Services\EmployeeService;
use Modules\Transaction\Models\LedgerTransaction;

readonly class SociBizAgentToolExecutor
{
    public function __construct(
        private LoanService $loanService,
        private LoanOverviewTooltipService $loanOverview,
        private RentalService $rentalService,
        private BillService $billService,
        private EmployeeService $employeeService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(User $user, ?Business $business, string $name, array $args): array
    {
        try {
            return match ($name) {
                'soci_biz_workspace_overview' => $this->workspaceOverview($user, $business),
                'soci_biz_list_accounts' => $this->listAccounts($user, $business),
                'soci_biz_list_loans' => $this->listLoans($user, $business),
                'soci_biz_list_rentals' => $this->listRentals($user, $business),
                'soci_biz_list_bills' => $this->listBills($user, $business),
                'soci_biz_list_recent_transactions' => $this->listRecentTransactions($user, $business, $args),
                'soci_biz_list_employees' => $this->listEmployees($user, $business),
                'soci_biz_list_departments' => $this->listDepartments($user, $business),
                'soci_biz_list_job_titles' => $this->listJobTitles($user, $business),
                'soci_biz_list_branches' => $this->listBranches($user, $business),
                'soci_biz_prepare_bill_draft' => $this->prepareBillDraft($user, $business, $args),
                'soci_biz_confirm_bill_insert' => $this->confirmBillInsert($user, $business, $args),
                default => ['error' => 'Unknown tool: '.$name],
            };
        } catch (\Throwable $e) {
            return ['error' => 'Tool failed: '.$e->getMessage()];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function functionDeclarations(): array
    {
        return [
            [
                'name' => 'soci_biz_workspace_overview',
                'description' => 'Summary of the signed-in user, selected business, display currency, HR payroll opt-in, and record counts (accounts, loans, rentals, bills, employees, departments, job titles, branches). Use this alone for “how many departments/employees/…” when counts are enough. Call first when you need grounding.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'soci_biz_list_accounts',
                'description' => 'Bank / cash accounts for the current business with balances and bank labels.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'soci_biz_list_loans',
                'description' => 'Loans for the business: principal, cadence, approximate monthly service, overdue flag.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'soci_biz_list_rentals',
                'description' => 'Rentals / leases: property, cadence, recurring cost, overdue flag, next billing hint.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'soci_biz_list_bills',
                'description' => 'Recurring or one-off bills for the business with overdue flag (per SociBiz rules).',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'soci_biz_list_recent_transactions',
                'description' => 'Recent ledger transactions (loan/rental/bill payments) with amounts and source labels.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Max rows (default 25, max 75).',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'soci_biz_list_employees',
                'description' => 'Employees for HR — only populated when payroll/HR onboarding is opted in.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'soci_biz_list_departments',
                'description' => 'Lists department names and ids for HR. Includes `count`. Use soci_biz_workspace_overview first for “how many departments” unless the user needs names.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'soci_biz_list_job_titles',
                'description' => 'Job titles / designations for HR.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'soci_biz_list_branches',
                'description' => 'Branches / warehouses when multi-location mode is enabled for the business.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'soci_biz_prepare_bill_draft',
                'description' => 'Creates/updates a draft bill payload from user-provided details and returns missing required fields. Use before confirmation.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'draft_id' => ['type' => 'string', 'description' => 'Optional existing draft id to continue updating same draft'],
                        'name' => ['type' => 'string'],
                        'payment_mode' => ['type' => 'string', 'description' => 'recurring or one_time'],
                        'bill_category' => ['type' => 'string', 'description' => 'water, electricity, telephone, internet, gas, waste, other'],
                        'bill_category_other' => ['type' => 'string'],
                        'recurring_cost' => ['type' => 'number'],
                        'recurring_type' => ['type' => 'string', 'description' => 'per_day, per_month, per_year'],
                        'agreement_valid_until_year' => ['type' => 'integer'],
                        'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'first_installment_due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'description' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                        'deduct_account_id' => ['type' => 'integer'],
                        'deduct_account_number' => ['type' => 'string', 'description' => 'Bank account number of deduct account (alternative to deduct_account_id)'],
                        'account_number' => ['type' => 'string'],
                        'account_no' => ['type' => 'string'],
                        'amount_varies_by_usage' => ['type' => 'boolean'],
                        'allow_split_payment' => ['type' => 'boolean'],
                        'remind_before_days' => ['type' => 'integer'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'soci_biz_confirm_bill_insert',
                'description' => 'Inserts the prepared draft bill after explicit user confirmation.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'draft_id' => ['type' => 'string'],
                        'confirm' => ['type' => 'boolean'],
                        'name' => ['type' => 'string'],
                        'payment_mode' => ['type' => 'string'],
                        'bill_category' => ['type' => 'string'],
                        'bill_category_other' => ['type' => 'string'],
                        'recurring_cost' => ['type' => 'number'],
                        'recurring_type' => ['type' => 'string'],
                        'agreement_valid_until_year' => ['type' => 'integer'],
                        'due_date' => ['type' => 'string'],
                        'first_installment_due_date' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                        'deduct_account_id' => ['type' => 'integer'],
                        'deduct_account_number' => ['type' => 'string'],
                        'account_number' => ['type' => 'string'],
                        'account_no' => ['type' => 'string'],
                        'amount_varies_by_usage' => ['type' => 'boolean'],
                        'allow_split_payment' => ['type' => 'boolean'],
                        'remind_before_days' => ['type' => 'integer'],
                    ],
                    'required' => ['draft_id', 'confirm'],
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function workspaceOverview(User $user, ?Business $business): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return [
                'user' => $user->only(['id', 'name', 'email']),
                'business' => null,
                'message' => 'No business selected or access denied — ask the user to pick a business in the header dropdown.',
            ];
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $hrOptedIn = (bool) get_settings('hr.payroll.opted_in', false, $business);

        return [
            'user' => $user->only(['id', 'name', 'email']),
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'category' => $business->category,
                'display_currency_setting' => $currency,
                'multi_warehouse_branch_enabled' => $business->multiWarehouseBranchEnabled(),
            ],
            'hr_payroll_opted_in' => $hrOptedIn,
            'counts' => [
                'accounts' => Account::query()->where('user_id', $user->id)->where('business_id', $business->id)->count(),
                'loans' => $business->loans()->count(),
                'rentals' => $business->rentals()->count(),
                'bills' => $business->bills()->count(),
                'employees' => $business->employees()->count(),
                'departments' => $business->departments()->count(),
                'job_titles' => $business->jobTitles()->count(),
                'branches' => $business->branches()->count(),
            ],
            'rules' => 'Use tools for facts; never invent balances or overdue status. Read-only — you cannot post payments from chat.',
        ];
    }

    private function listAccounts(User $user, ?Business $business): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        $rows = Account::query()
            ->with(['bankType', 'bank'])
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();

        return [
            'currency_setting' => (string) (get_settings('business.currency', '', $business) ?: ''),
            'accounts' => $rows->map(fn (Account $a) => [
                'id' => $a->id,
                'label' => $a->deductOptionLabel(),
                'current_balance' => (string) $a->current_balance,
            ])->values()->all(),
        ];
    }

    private function listLoans(User $user, ?Business $business): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        $loans = $this->loanService->listForBusiness($business);
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $out = [];

        foreach ($loans as $loan) {
            $summary = $this->loanOverview->summarizeLoan($loan);
            $out[] = [
                'id' => $loan->id,
                'name' => $loan->name,
                'borrowed_amount' => (string) $loan->borrowed_amount,
                'recurring_type' => $loan->recurring_type,
                'approx_monthly_service' => $summary['approx_monthly'] ?? null,
                'has_overdue_installment' => $this->loanOverview->loanHasOverdueInstallments($loan),
                'detail_url_hint' => 'account.loans.show',
            ];
        }

        return ['currency_setting' => $currency, 'loans' => $out];
    }

    private function listRentals(User $user, ?Business $business): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $overdueMap = $this->rentalService->rentalOverdueMapForBusiness($business);
        $rentals = $this->rentalService->listForBusiness($business);
        $out = [];

        foreach ($rentals as $rental) {
            $insight = $this->rentalService->nextPaymentInsight($rental);
            $out[] = [
                'id' => $rental->id,
                'title' => $rental->purpose ?: $rental->property_type,
                'property_type' => $rental->property_type,
                'recurring_type' => $rental->recurring_type,
                'recurring_cost' => (string) $rental->recurring_cost,
                'overdue_billing_flag' => (bool) ($overdueMap[$rental->id] ?? false),
                'next_billing_days_until' => $insight ? $insight['days_until'] : null,
                'next_billing_date' => $insight ? $insight['next_date']->toDateString() : null,
            ];
        }

        return ['currency_setting' => $currency, 'rentals' => $out];
    }

    private function listBills(User $user, ?Business $business): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $overdueMap = $this->billService->billOverdueMapForBusiness($business);
        $bills = $this->billService->listForBusiness($business);
        $out = [];

        foreach ($bills as $bill) {
            $insight = $this->billService->nextPaymentInsight($bill);
            $out[] = [
                'id' => $bill->id,
                'name' => $bill->name,
                'recurring_type' => $bill->recurring_type,
                'recurring_cost' => (string) $bill->recurring_cost,
                'overdue_flag' => (bool) ($overdueMap[$bill->id] ?? false),
                'next_payment_days_until' => $insight ? $insight['days_until'] : null,
                'next_payment_date' => $insight ? $insight['next_date']->toDateString() : null,
            ];
        }

        return ['currency_setting' => $currency, 'bills' => $out];
    }

    /** @param  array<string, mixed>  $args */
    private function listRecentTransactions(User $user, ?Business $business, array $args): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        $limit = (int) ($args['limit'] ?? 25);
        $limit = max(1, min(75, $limit));

        $rows = LedgerTransaction::query()
            ->where('business_id', $business->id)
            ->with([
                'transactionable',
                'deductAccount.bankType',
                'deductAccount.bank',
            ])
            ->orderByDesc('occurrence_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'limit' => $limit,
            'transactions' => $rows->map(function (LedgerTransaction $row) {
                return [
                    'id' => $row->id,
                    'occurrence_date' => $row->occurrence_date?->toDateString(),
                    'amount' => (string) $row->amount,
                    'currency' => $row->currency,
                    'source_kind' => $row->sourceKindLabel(),
                    'source_title' => $row->sourceTitle(),
                    'deduct_account_label' => $row->deductAccount ? $row->deductAccount->deductOptionLabel() : null,
                    'period_number' => $row->period_number,
                ];
            })->values()->all(),
        ];
    }

    private function listEmployees(User $user, ?Business $business): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        if (! $this->hrOptedIn($business)) {
            return [
                'employees' => [],
                'note' => 'HR payroll is not opted in — complete HR setup in the workspace to access employees.',
            ];
        }

        $employees = $this->employeeService->listForBusiness($business);

        return [
            'employees' => $employees->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'employment_type' => $e->employment_type,
                'date_of_joining' => $e->date_of_joining?->toDateString(),
                'department' => $e->department?->name,
                'job_title' => $e->jobTitle?->name,
                'employee_id_ref' => $e->employee_id,
            ])->values()->all(),
        ];
    }

    private function listDepartments(User $user, ?Business $business): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        if (! $this->hrOptedIn($business)) {
            $deptCount = $business->departments()->count();

            return [
                'count' => $deptCount,
                'departments' => [],
                'note' => 'HR payroll not opted in — name list is hidden here; use soci_biz_workspace_overview `counts.departments` for the total, or complete HR setup to list names.',
            ];
        }

        $rows = $business->departments()->orderBy('name')->get(['id', 'name']);

        return [
            'count' => $rows->count(),
            'departments' => $rows->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all(),
        ];
    }

    private function listJobTitles(User $user, ?Business $business): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        if (! $this->hrOptedIn($business)) {
            return ['job_titles' => [], 'note' => 'HR payroll not opted in.'];
        }

        $rows = $business->jobTitles()->orderBy('name')->get(['id', 'name']);

        return [
            'job_titles' => $rows->map(fn ($j) => ['id' => $j->id, 'name' => $j->name])->all(),
        ];
    }

    private function listBranches(User $user, ?Business $business): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        if (! $business->multiWarehouseBranchEnabled()) {
            return [
                'branches' => [],
                'note' => 'Multi-warehouse / branch mode is disabled for this business.',
            ];
        }

        $rows = $business->branches()->orderBy('name')->get(['id', 'name']);

        return [
            'branches' => $rows->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function prepareBillDraft(User $user, ?Business $business, array $args): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        $incomingDraftId = trim((string) ($args['draft_id'] ?? ''));
        $draftId = $incomingDraftId !== '' ? $incomingDraftId : $this->latestBillDraftId($user, $business);
        $baseDraft = $draftId !== null ? $this->getBillDraft($user, $business, $draftId) : null;
        $patch = $this->normalizeBillDraftArgs($user, $business, $args);
        $draft = is_array($baseDraft) ? $this->mergeBillDraft($baseDraft, $patch) : $patch;
        $missing = $this->missingRequiredBillFields($draft);
        $draftId = $this->storeBillDraft($user, $business, $draft, $draftId);

        return [
            'draft_id' => $draftId,
            'ready_to_confirm' => $missing === [],
            'missing_fields' => $missing,
            'allowed_values' => [
                'payment_mode' => array_keys(Bill::paymentModes()),
                'bill_category' => array_keys(Bill::billCategories()),
                'recurring_type' => array_keys(Bill::recurringTypes()),
            ],
            'draft' => $draft,
            'message' => $missing === []
                ? 'Draft prepared. Ask user to confirm insertion, then call soci_biz_confirm_bill_insert.'
                : 'Draft saved. Ask user for missing fields.',
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function confirmBillInsert(User $user, ?Business $business, array $args): array
    {
        if (! $this->ownsBusiness($user, $business)) {
            return $this->needBusiness();
        }

        $draftId = trim((string) ($args['draft_id'] ?? ''));
        $confirm = (bool) ($args['confirm'] ?? false);
        if ($draftId === '') {
            $draftId = $this->latestBillDraftId($user, $business) ?? '';
        }
        if ($draftId === '') {
            return ['error' => 'No draft found. Please prepare draft first.'];
        }
        if (! $confirm) {
            return ['status' => 'cancelled', 'message' => 'Insert cancelled because confirm=false.'];
        }

        $draft = $this->getBillDraft($user, $business, $draftId);
        if (! is_array($draft)) {
            $fallbackDraft = $this->normalizeBillDraftArgs($user, $business, $args);
            $fallbackMissing = $this->missingRequiredBillFields($fallbackDraft);
            if ($fallbackMissing !== []) {
                return [
                    'error' => 'Draft not found or expired.',
                    'message' => 'Draft expired. Please provide missing fields or ask to prepare draft again.',
                    'missing_fields' => $fallbackMissing,
                ];
            }
            $draft = $fallbackDraft;
        }

        $missing = $this->missingRequiredBillFields($draft);
        if ($missing !== []) {
            return ['error' => 'Draft is incomplete.', 'missing_fields' => $missing, 'draft' => $draft];
        }

        $bill = $this->billService->create($user, $business, $draft);
        Cache::forget($this->billDraftCacheKey($user, $business, $draftId));
        Cache::forget($this->latestBillDraftKey($user, $business));

        return [
            'status' => 'inserted',
            'bill' => [
                'id' => $bill->id,
                'name' => $bill->name,
                'payment_mode' => $bill->payment_mode,
                'bill_category' => $bill->bill_category,
                'recurring_cost' => (string) $bill->recurring_cost,
            ],
            'message' => 'Bill inserted successfully.',
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function normalizeBillDraftArgs(User $user, Business $business, array $args): array
    {
        $paymentMode = (string) ($args['payment_mode'] ?? Bill::PAYMENT_MODE_RECURRING);
        if (! in_array($paymentMode, array_keys(Bill::paymentModes()), true)) {
            $paymentMode = Bill::PAYMENT_MODE_RECURRING;
        }

        $category = (string) ($args['bill_category'] ?? Bill::CATEGORY_OTHER);
        if (! in_array($category, array_keys(Bill::billCategories()), true)) {
            $category = Bill::CATEGORY_OTHER;
        }

        $recurringType = (string) ($args['recurring_type'] ?? Bill::RECURRING_PER_MONTH);
        if (! in_array($recurringType, array_keys(Bill::recurringTypes()), true)) {
            $recurringType = Bill::RECURRING_PER_MONTH;
        }

        $dueDate = $this->normalizeDateYmd($args['due_date'] ?? null);
        $firstInstallment = $this->normalizeDateYmd($args['first_installment_due_date'] ?? null);
        $agreementYear = isset($args['agreement_valid_until_year']) ? (int) $args['agreement_valid_until_year'] : null;
        if ($paymentMode === Bill::PAYMENT_MODE_ONE_TIME && $agreementYear === null) {
            $anchor = $dueDate ?: $firstInstallment;
            if ($anchor !== null) {
                $agreementYear = (int) substr($anchor, 0, 4);
            }
        }

        $resolvedAccountId = $this->resolveDeductAccountId($user, $business, $args);

        return [
            'name' => trim((string) ($args['name'] ?? '')),
            'payment_mode' => $paymentMode,
            'bill_category' => $category,
            'bill_category_other' => $category === Bill::CATEGORY_OTHER ? trim((string) ($args['bill_category_other'] ?? '')) : null,
            'description' => $this->nullableTrimmed($args['description'] ?? null),
            'agreement_valid_until_year' => $agreementYear,
            'branch_id' => null,
            'department_id' => null,
            'rental_property_related' => false,
            'rental_id' => null,
            'deduct_account_id' => $resolvedAccountId,
            'amount_varies_by_usage' => (bool) ($args['amount_varies_by_usage'] ?? false),
            'allow_split_payment' => (bool) ($args['allow_split_payment'] ?? true),
            'recurring_cost' => round((float) ($args['recurring_cost'] ?? 0), 2),
            'recurring_type' => $recurringType,
            'notes' => $this->nullableTrimmed($args['notes'] ?? null),
            'remind_before_days' => isset($args['remind_before_days']) ? max(0, (int) $args['remind_before_days']) : null,
            'due_date' => $dueDate,
            'first_installment_due_date' => $firstInstallment,
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function resolveDeductAccountId(User $user, Business $business, array $args): ?int
    {
        $rawId = $args['deduct_account_id'] ?? null;
        if (is_int($rawId) || (is_string($rawId) && ctype_digit(trim($rawId)))) {
            $id = (int) $rawId;
            if ($id > 0) {
                $exists = Account::query()
                    ->where('id', $id)
                    ->where('user_id', $user->id)
                    ->where('business_id', $business->id)
                    ->exists();
                if ($exists) {
                    return $id;
                }
            }
        }

        $candidates = [
            $args['deduct_account_number'] ?? null,
            $args['account_number'] ?? null,
            $args['account_no'] ?? null,
        ];
        if (is_string($rawId) && trim($rawId) !== '' && ! ctype_digit(trim($rawId))) {
            $candidates[] = $rawId;
        }

        foreach ($candidates as $candidate) {
            $accountNumber = trim((string) ($candidate ?? ''));
            if ($accountNumber === '') {
                continue;
            }

            $resolved = Account::query()
                ->where('user_id', $user->id)
                ->where('business_id', $business->id)
                ->where('bank_account_number', $accountNumber)
                ->value('id');

            if ($resolved !== null) {
                return (int) $resolved;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $draft */
    private function missingRequiredBillFields(array $draft): array
    {
        $missing = [];
        if (trim((string) ($draft['name'] ?? '')) === '') {
            $missing[] = 'name';
        }

        $mode = (string) ($draft['payment_mode'] ?? '');
        if ($mode === '') {
            $missing[] = 'payment_mode';
        }

        $category = (string) ($draft['bill_category'] ?? '');
        if ($category === '') {
            $missing[] = 'bill_category';
        }
        if ($category === Bill::CATEGORY_OTHER && trim((string) ($draft['bill_category_other'] ?? '')) === '') {
            $missing[] = 'bill_category_other';
        }

        $amountVaries = (bool) ($draft['amount_varies_by_usage'] ?? false);
        if (! $amountVaries && (float) ($draft['recurring_cost'] ?? 0) <= 0) {
            $missing[] = 'recurring_cost';
        }

        if ($mode === Bill::PAYMENT_MODE_RECURRING) {
            if ((int) ($draft['agreement_valid_until_year'] ?? 0) <= 0) {
                $missing[] = 'agreement_valid_until_year';
            }
            if (trim((string) ($draft['recurring_type'] ?? '')) === '') {
                $missing[] = 'recurring_type';
            }
        }
        if ($mode === Bill::PAYMENT_MODE_ONE_TIME
            && trim((string) ($draft['due_date'] ?? '')) === ''
            && trim((string) ($draft['first_installment_due_date'] ?? '')) === '') {
            $missing[] = 'due_date_or_first_installment_due_date';
        }

        return array_values(array_unique($missing));
    }

    /** @param  array<string, mixed>  $draft */
    private function storeBillDraft(User $user, Business $business, array $draft, ?string $draftId = null): string
    {
        $draftId = $draftId !== null && $draftId !== '' ? $draftId : (string) Str::uuid();
        Cache::put($this->billDraftCacheKey($user, $business, $draftId), $draft, now()->addHours(12));
        Cache::put($this->latestBillDraftKey($user, $business), $draftId, now()->addHours(12));

        return $draftId;
    }

    private function billDraftCacheKey(User $user, Business $business, string $draftId): string
    {
        return 'aibot:bill_draft:'.$user->id.':'.$business->id.':'.$draftId;
    }

    private function latestBillDraftKey(User $user, Business $business): string
    {
        return 'aibot:bill_draft_latest:'.$user->id.':'.$business->id;
    }

    private function latestBillDraftId(User $user, Business $business): ?string
    {
        $id = Cache::get($this->latestBillDraftKey($user, $business));
        if (! is_string($id) || trim($id) === '') {
            return null;
        }

        return trim($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getBillDraft(User $user, Business $business, string $draftId): ?array
    {
        $draft = Cache::get($this->billDraftCacheKey($user, $business, $draftId));
        if (is_array($draft)) {
            // Sliding expiration while the user continues the bill-insert conversation.
            Cache::put($this->billDraftCacheKey($user, $business, $draftId), $draft, now()->addHours(12));
            Cache::put($this->latestBillDraftKey($user, $business), $draftId, now()->addHours(12));
        }

        return is_array($draft) ? $draft : null;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function mergeBillDraft(array $base, array $patch): array
    {
        foreach ($patch as $k => $v) {
            if (is_string($v) && trim($v) === '') {
                continue;
            }
            if ($v === null) {
                continue;
            }
            $base[$k] = $v;
        }

        if (($base['bill_category'] ?? null) !== Bill::CATEGORY_OTHER) {
            $base['bill_category_other'] = null;
        }

        return $base;
    }

    private function normalizeDateYmd(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s === '' ? null : $s;
    }

    private function ownsBusiness(User $user, ?Business $business): bool
    {
        return $business !== null && $user->businesses()->whereKey($business->id)->exists();
    }

    private function hrOptedIn(?Business $business): bool
    {
        return $business !== null && (bool) get_settings('hr.payroll.opted_in', false, $business);
    }

    /** @return array<string, string> */
    private function needBusiness(): array
    {
        return ['error' => 'No authorised business selected. Ask the user to choose their business from the SociBiz header.'];
    }
}
