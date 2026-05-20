<?php

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Account\Models\Bank;
use Modules\Business\Models\Business;
use Modules\HRManagement\Mail\EmployeePortalWelcomeMail;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\EmployeeDocument;
use Modules\HRManagement\Models\JobTitle;
use Modules\HRManagement\Models\PayrollItem;
use Modules\HRManagement\Services\DepartmentService;
use Modules\HRManagement\Services\EmployeeDocumentService;
use Modules\HRManagement\Services\EmployeeLeaveBalanceService;
use Modules\HRManagement\Services\EmployeeOverviewMetricsService;
use Modules\HRManagement\Services\EmployeePortalProvisioningService;
use Modules\HRManagement\Services\EmployeeProfilePhotoService;
use Modules\HRManagement\Services\EmployeeService;
use Modules\HRManagement\Services\HrPayrollSettingsService;
use Modules\HRManagement\Services\JobTitleService;

class HrEmployeeController extends Controller
{
    public function __construct(
        private readonly HrPayrollSettingsService $hrPayrollSettings,
        private readonly EmployeeService $employeeService,
        private readonly DepartmentService $departmentService,
        private readonly JobTitleService $jobTitleService,
        private readonly EmployeeOverviewMetricsService $employeeOverviewMetrics,
        private readonly EmployeeLeaveBalanceService $employeeLeaveBalance,
        private readonly EmployeeProfilePhotoService $employeeProfilePhoto,
        private readonly EmployeeDocumentService $employeeDocumentService,
        private readonly EmployeePortalProvisioningService $employeePortalProvisioning,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        return view('hrmanagement::employees.index', [
            'business' => $business,
            'employees' => $this->employeeService->listForBusiness($business),
            'departments' => $business->departments()->get(),
            'jobTitles' => $business->jobTitles()->get(),
            'allowanceTypes' => $business->allowanceTypes()->get(),
            'banks' => Bank::query()->orderBy('name')->get(),
            'employmentTypeLabels' => [
                Employee::EMPLOYMENT_FULL_TIME => 'Full-Time',
                Employee::EMPLOYMENT_PART_TIME => 'Part-Time',
                Employee::EMPLOYMENT_CONTRACT => 'Contract',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $validator = Validator::make($request->all(), $this->employeeFieldRules($business));

        $validator->after(function ($validator) use ($business): void {
            $data = $validator->getData();

            if (! $validator->errors()->has('basic_salary') && ! $validator->errors()->has('salary')) {
                $basic = round((float) ($data['basic_salary'] ?? 0), 2);
                $allowanceRaw = $data['allowances'] ?? [];
                if (! is_array($allowanceRaw)) {
                    $allowanceRaw = [];
                }
                $expected = $basic;
                $allowanceInputsOk = true;
                foreach ($business->allowanceTypes()->pluck('id')->all() as $typeId) {
                    $raw = $allowanceRaw[(string) $typeId] ?? $allowanceRaw[$typeId] ?? null;
                    if ($raw === null || $raw === '') {
                        continue;
                    }
                    if (! is_numeric($raw)) {
                        $validator->errors()->add('allowances.'.$typeId, __('Enter a valid amount for each allowance.'));
                        $allowanceInputsOk = false;

                        continue;
                    }
                    $expected += round(max(0, (float) $raw), 2);
                }
                if ($allowanceInputsOk) {
                    $declared = round((float) ($data['salary'] ?? 0), 2);
                    if (abs($declared - $expected) > 0.02) {
                        $validator->errors()->add(
                            'salary',
                            __('Monthly gross must equal basic salary plus allowance amounts (expected :expected).', [
                                'expected' => number_format($expected, 2, '.', ''),
                            ])
                        );
                    }
                }
            }

            $departmentChoice = isset($data['department_id']) ? (string) $data['department_id'] : '';
            if ($departmentChoice === '') {
                $validator->errors()->add('department_id', __('Choose a department.'));
            } elseif ($departmentChoice === Employee::SELECT_NEW_ROW) {
                $name = trim((string) ($data['new_department_name'] ?? ''));
                if ($name === '') {
                    $validator->errors()->add('new_department_name', __('Enter the new department name.'));
                } elseif (Department::query()->where('business_id', $business->id)->where('name', $name)->exists()) {
                    $validator->errors()->add('new_department_name', __('That department already exists for this business.'));
                }
            } elseif (! ctype_digit($departmentChoice)) {
                $validator->errors()->add('department_id', __('Choose an existing department or add a new one.'));
            } elseif (! Department::query()->where('business_id', $business->id)->whereKey((int) $departmentChoice)->exists()) {
                $validator->errors()->add('department_id', __('That department does not belong to this business.'));
            }

            $jobTitleChoice = isset($data['job_title_id']) ? (string) $data['job_title_id'] : '';
            if ($jobTitleChoice === '') {
                $validator->errors()->add('job_title_id', __('Choose a job title or designation.'));
            } elseif ($jobTitleChoice === Employee::SELECT_NEW_ROW) {
                $name = trim((string) ($data['new_job_title_name'] ?? ''));
                if ($name === '') {
                    $validator->errors()->add('new_job_title_name', __('Enter the new job title or designation.'));
                } elseif (JobTitle::query()->where('business_id', $business->id)->where('name', $name)->exists()) {
                    $validator->errors()->add('new_job_title_name', __('That job title already exists for this business.'));
                }
            } elseif (! ctype_digit($jobTitleChoice)) {
                $validator->errors()->add('job_title_id', __('Choose an existing job title or add a new one.'));
            } elseif (! JobTitle::query()->where('business_id', $business->id)->whereKey((int) $jobTitleChoice)->exists()) {
                $validator->errors()->add('job_title_id', __('That job title does not belong to this business.'));
            }
        });

        /** @throws ValidationException */
        $validated = $validator->validate();

        [$employee, $portalProvision] = DB::transaction(function () use ($business, $validated): array {
            $payload = $validated;

            if ((string) $payload['department_id'] === Employee::SELECT_NEW_ROW) {
                $payload['department_id'] = $this->departmentService->create(
                    $business,
                    (string) $payload['new_department_name'],
                )->id;
            } else {
                $payload['department_id'] = (int) $payload['department_id'];
            }

            if ((string) $payload['job_title_id'] === Employee::SELECT_NEW_ROW) {
                $payload['job_title_id'] = $this->jobTitleService->create(
                    $business,
                    (string) $payload['new_job_title_name'],
                )->id;
            } else {
                $payload['job_title_id'] = (int) $payload['job_title_id'];
            }

            unset($payload['new_department_name'], $payload['new_job_title_name'], $payload['profile_photo']);

            $employee = $this->employeeService->create($business, $payload);
            $provision = $this->employeePortalProvisioning->provisionPortalAccess($employee, $business);

            return [$employee->fresh(), $provision];
        });

        if ($request->hasFile('profile_photo')) {
            $this->employeeProfilePhoto->store($employee, $request->file('profile_photo'));
        }

        if (($portalProvision['scenario'] ?? '') !== 'noop') {
            try {
                Mail::to(Str::lower(trim($employee->personal_email)))->send(new EmployeePortalWelcomeMail(
                    $employee,
                    $business,
                    route('hr.portal.login', [], true),
                    $portalProvision,
                ));
            } catch (\Throwable $e) {
                Log::error('Employee HR portal welcome email failed.', [
                    'exception' => $e,
                    'employee_id' => $employee->id,
                ]);

                return redirect()->route('hr.employees.index')->with(
                    'warning',
                    __('Employee created, but the welcome email could not be sent. Share the HR portal link and access details manually.')
                );
            }
        }

        return redirect()->route('hr.employees.index')->with('status', __('Employee created.'));
    }

    public function show(Request $request, Employee $employee): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_if((int) $employee->business_id !== (int) $business->id, 404);

        $employee->load(['bank', 'department', 'jobTitle', 'employeeAllowances.allowanceType', 'documents', 'leaveRequests']);

        $employeePayslips = PayrollItem::query()
            ->where('employee_id', $employee->id)
            ->whereHas('cycle', fn ($q) => $q->where('business_id', $business->id))
            ->with(['cycle:id,name,year,month,status,finalized_at'])
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return view('hrmanagement::employees.show', [
            'business' => $business,
            'employee' => $employee,
            'employeePayslips' => $employeePayslips,
            'overviewMetrics' => $this->employeeOverviewMetrics->forEmployee($business, $employee),
            'documentCategories' => EmployeeDocument::CATEGORIES,
            'leaveBalanceSummary' => $this->employeeLeaveBalance->yearlySummary($business, $employee),
            'banks' => Bank::query()->orderBy('name')->get(),
            'hrEditDepartments' => $business->departments()->orderBy('name')->get(),
            'hrEditJobTitles' => $business->jobTitles()->orderBy('name')->get(),
            'employmentTypeLabels' => [
                Employee::EMPLOYMENT_FULL_TIME => __('Full-Time'),
                Employee::EMPLOYMENT_PART_TIME => __('Part-Time'),
                Employee::EMPLOYMENT_CONTRACT => __('Contract'),
            ],
        ]);
    }

    public function storeProfilePhoto(Request $request, Employee $employee): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }
        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_if((int) $employee->business_id !== (int) $business->id, 404);

        $request->validate([
            'profile_photo' => ['required', 'image', 'max:4096'],
        ]);

        $this->employeeProfilePhoto->store($employee, $request->file('profile_photo'));

        return redirect()->route('hr.employees.show', $employee)->with('status', __('Profile photo updated.'));
    }

    public function destroyProfilePhoto(Request $request, Employee $employee): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }
        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_if((int) $employee->business_id !== (int) $business->id, 404);

        $this->employeeProfilePhoto->delete($employee);

        return redirect()->route('hr.employees.show', $employee)->with('status', __('Profile photo removed.'));
    }

    public function storeDocument(Request $request, Employee $employee): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }
        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_if((int) $employee->business_id !== (int) $business->id, 404);

        $validated = $request->validate([
            'document_category' => ['required', 'string', Rule::in(EmployeeDocument::CATEGORIES)],
            'document_file' => [
                'required',
                'file',
                'max:15360',
                'mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp,txt,rtf,xlsx,xls,ppt,pptx,csv',
            ],
        ]);

        $this->employeeDocumentService->store(
            $employee,
            $request->file('document_file'),
            $validated['document_category'],
            $request->user()->id,
        );

        return redirect()->to(route('hr.employees.show', $employee).'#documents')
            ->with('status', __('Document uploaded.'));
    }

    public function downloadDocument(Request $request, Employee $employee, EmployeeDocument $document)
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }
        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_if((int) $employee->business_id !== (int) $business->id, 404);
        abort_unless((int) $document->employee_id === (int) $employee->id, 404);
        abort_unless((int) $document->business_id === (int) $business->id, 404);
        abort_unless(Storage::disk('public')->exists($document->stored_path), 404);

        return Storage::disk('public')->download($document->stored_path, $document->original_filename);
    }

    public function destroyDocument(Request $request, Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }
        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_if((int) $employee->business_id !== (int) $business->id, 404);
        abort_unless((int) $document->employee_id === (int) $employee->id, 404);
        abort_unless((int) $document->business_id === (int) $business->id, 404);

        $this->employeeDocumentService->delete($document);

        return redirect()->to(route('hr.employees.show', $employee).'#documents')
            ->with('status', __('Document removed.'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_if((int) $employee->business_id !== (int) $business->id, 404);

        $request->validate([
            'field' => ['required', 'string', Rule::in($this->patchableFieldKeys())],
            '_panel' => ['nullable', 'string', 'max:40'],
        ]);

        $field = (string) $request->input('field');
        $panel = (string) ($request->input('_panel') ?: 'overview');
        $panel = preg_match('/^[a-z0-9_-]{1,40}$/', $panel) ? $panel : 'overview';

        $validated = $request->validate($this->patchFieldValidationRules($business, $employee, $field));

        if ($field === 'employee_allowance') {
            $this->employeeService->updateAllowanceAmount(
                $employee,
                (int) $validated['employee_allowance_id'],
                (float) $validated['allowance_amount'],
            );

            return $this->redirectToEmployeeShowPanel($employee, $panel, __('Allowance updated.'));
        }

        $payload = [];
        foreach ($validated as $key => $value) {
            if (in_array($key, ['epf_number', 'etf_number', 'tax_tin'], true)) {
                $payload[$key] = ($value === '' || $value === null) ? null : $value;

                continue;
            }
            if ($key === 'bank_id' || $key === 'job_title_id' || $key === 'department_id') {
                $payload[$key] = (int) $value;

                continue;
            }
            if ($key === 'basic_salary') {
                $payload[$key] = round((float) $value, 2);

                continue;
            }
            $payload[$key] = $value;
        }

        $employee->fill($payload);
        $employee->save();

        if ($field === 'basic_salary') {
            $this->employeeService->recalculateMonthlyGross($employee->fresh());
        }

        return $this->redirectToEmployeeShowPanel($employee, $panel, __('Updated.'));
    }

    /** @return list<string> */
    private function patchableFieldKeys(): array
    {
        return [
            'full_name',
            'date_of_birth',
            'nic_passport_number',
            'permanent_address',
            'current_address',
            'phone_number',
            'personal_email',
            'employee_id',
            'job_title_id',
            'department_id',
            'date_of_joining',
            'employment_type',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_phone',
            'bank_account_holder_name',
            'bank_id',
            'bank_branch',
            'bank_account_number',
            'epf_number',
            'etf_number',
            'tax_tin',
            'basic_salary',
            'employee_allowance',
        ];
    }

    /** @return array<string, mixed> */
    private function patchFieldValidationRules(Business $business, Employee $employee, string $field): array
    {
        $nicUnique = Rule::unique('hr_employees', 'nic_passport_number')
            ->where(fn ($query) => $query->where('business_id', $business->id))
            ->ignore($employee->id);

        $employeeCodeUnique = Rule::unique('hr_employees', 'employee_id')
            ->where(fn ($query) => $query->where('business_id', $business->id))
            ->ignore($employee->id);

        return match ($field) {
            'full_name' => ['full_name' => ['required', 'string', 'max:255']],
            'date_of_birth' => ['date_of_birth' => ['required', 'date', 'before:today']],
            'nic_passport_number' => ['nic_passport_number' => ['required', 'string', 'max:64', $nicUnique]],
            'permanent_address' => ['permanent_address' => ['required', 'string', 'max:5000']],
            'current_address' => ['current_address' => ['required', 'string', 'max:5000']],
            'phone_number' => ['phone_number' => ['required', 'string', 'max:40']],
            'personal_email' => ['personal_email' => ['required', 'email', 'max:255']],
            'employee_id' => ['employee_id' => ['required', 'string', 'max:64', $employeeCodeUnique]],
            'job_title_id' => [
                'job_title_id' => [
                    'required',
                    'integer',
                    Rule::exists('hr_job_titles', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
                ],
            ],
            'department_id' => [
                'department_id' => [
                    'required',
                    'integer',
                    Rule::exists('hr_departments', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
                ],
            ],
            'date_of_joining' => ['date_of_joining' => ['required', 'date']],
            'employment_type' => ['employment_type' => ['required', 'string', Rule::in(Employee::EMPLOYMENT_TYPES)]],
            'emergency_contact_name' => ['emergency_contact_name' => ['required', 'string', 'max:255']],
            'emergency_contact_relationship' => ['emergency_contact_relationship' => ['required', 'string', 'max:120']],
            'emergency_contact_phone' => ['emergency_contact_phone' => ['required', 'string', 'max:40']],
            'bank_account_holder_name' => ['bank_account_holder_name' => ['required', 'string', 'max:255']],
            'bank_id' => ['bank_id' => ['required', 'integer', 'exists:banks,id']],
            'bank_branch' => ['bank_branch' => ['required', 'string', 'max:255']],
            'bank_account_number' => ['bank_account_number' => ['required', 'string', 'max:64']],
            'epf_number' => ['epf_number' => ['nullable', 'string', 'max:80']],
            'etf_number' => ['etf_number' => ['nullable', 'string', 'max:80']],
            'tax_tin' => ['tax_tin' => ['nullable', 'string', 'max:80']],
            'basic_salary' => ['basic_salary' => ['required', 'numeric', 'min:0', 'max:999999999.99']],
            'employee_allowance' => [
                'employee_allowance_id' => [
                    'required',
                    'integer',
                    Rule::exists('hr_employee_allowances', 'id')->where(fn ($query) => $query->where('employee_id', $employee->id)),
                ],
                'allowance_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            ],
            default => abort(422, 'Unsupported field.'),
        };
    }

    private function redirectToEmployeeShowPanel(Employee $employee, string $panel, string $status): RedirectResponse
    {
        return redirect()
            ->to(route('hr.employees.show', $employee).'#'.$panel)
            ->with('status', $status);
    }

    /** @return array<string, mixed> */
    private function employeeFieldRules(Business $business): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'profile_photo' => ['nullable', 'image', 'max:4096'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'nic_passport_number' => [
                'required', 'string', 'max:64',
                Rule::unique('hr_employees', 'nic_passport_number')->where(
                    fn ($query) => $query->where('business_id', $business->id)
                ),
            ],
            'permanent_address' => ['required', 'string', 'max:5000'],
            'current_address' => ['required', 'string', 'max:5000'],
            'phone_number' => ['required', 'string', 'max:40'],
            'personal_email' => ['required', 'email', 'max:255'],

            'employee_id' => [
                'required', 'string', 'max:64',
                Rule::unique('hr_employees', 'employee_id')->where(
                    fn ($query) => $query->where('business_id', $business->id)
                ),
            ],
            'job_title_id' => ['required', 'string', 'max:64'],
            'new_job_title_name' => ['nullable', 'string', 'max:255'],
            'department_id' => ['required', 'string', 'max:64'],
            'new_department_name' => ['nullable', 'string', 'max:255'],
            'date_of_joining' => ['required', 'date'],
            'employment_type' => ['required', 'string', Rule::in(Employee::EMPLOYMENT_TYPES)],

            'basic_salary' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'salary' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'allowances' => ['nullable', 'array'],

            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_relationship' => ['required', 'string', 'max:120'],
            'emergency_contact_phone' => ['required', 'string', 'max:40'],

            'bank_account_holder_name' => ['required', 'string', 'max:255'],
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'bank_branch' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:64'],

            'epf_number' => ['nullable', 'string', 'max:80'],
            'etf_number' => ['nullable', 'string', 'max:80'],
            'tax_tin' => ['nullable', 'string', 'max:80'],
        ];
    }
}
