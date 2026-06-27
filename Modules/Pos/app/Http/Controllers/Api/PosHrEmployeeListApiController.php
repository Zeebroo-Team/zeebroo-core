<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\JobTitle;
use Modules\HRManagement\Services\DepartmentService;
use Modules\HRManagement\Services\EmployeeService;
use Modules\HRManagement\Services\JobTitleService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrEmployeeListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly DepartmentService $departmentService,
        private readonly JobTitleService $jobTitleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_employees')) {
            return response()->json([
                'data'            => [],
                'total_count'     => 0,
                'full_time_count' => 0,
                'part_time_count' => 0,
                'contract_count'  => 0,
            ]);
        }

        $employees = Employee::with(['department', 'jobTitle'])
            ->where('business_id', $business->id)
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'data'            => $employees->map(fn (Employee $e) => $this->format($e))->values(),
            'total_count'     => $employees->count(),
            'full_time_count' => $employees->where('employment_type', 'full_time')->count(),
            'part_time_count' => $employees->where('employment_type', 'part_time')->count(),
            'contract_count'  => $employees->where('employment_type', 'contract')->count(),
        ]);
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $employee->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        $employee->load(['department', 'jobTitle']);

        return response()->json(['data' => $this->format($employee, detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_employees')) {
            return response()->json(['message' => 'HR module is not set up.'], 422);
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'full_name'                       => ['required', 'string', 'max:255'],
            'date_of_birth'                   => ['required', 'date', 'before:today'],
            'nic_passport_number'             => [
                'required', 'string', 'max:64',
                Rule::unique('hr_employees', 'nic_passport_number')
                    ->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'permanent_address'               => ['required', 'string', 'max:5000'],
            'current_address'                 => ['required', 'string', 'max:5000'],
            'phone_number'                    => ['required', 'string', 'max:40'],
            'personal_email'                  => ['required', 'email', 'max:255'],
            'employee_id'                     => [
                'required', 'string', 'max:64',
                Rule::unique('hr_employees', 'employee_id')
                    ->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'job_title_id'                    => ['required', 'string', 'max:64'],
            'new_job_title_name'              => ['nullable', 'string', 'max:255'],
            'department_id'                   => ['required', 'string', 'max:64'],
            'new_department_name'             => ['nullable', 'string', 'max:255'],
            'date_of_joining'                 => ['required', 'date'],
            'employment_type'                 => ['required', 'string', Rule::in(Employee::EMPLOYMENT_TYPES)],
            'basic_salary'                    => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'salary'                          => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'emergency_contact_name'          => ['required', 'string', 'max:255'],
            'emergency_contact_relationship'  => ['required', 'string', 'max:120'],
            'emergency_contact_phone'         => ['required', 'string', 'max:40'],
            'bank_account_holder_name'        => ['required', 'string', 'max:255'],
            'bank_id'                         => ['required', 'integer', 'exists:banks,id'],
            'bank_branch'                     => ['required', 'string', 'max:255'],
            'bank_account_number'             => ['required', 'string', 'max:64'],
            'epf_number'                      => ['nullable', 'string', 'max:80'],
            'etf_number'                      => ['nullable', 'string', 'max:80'],
            'tax_tin'                         => ['nullable', 'string', 'max:80'],
        ]);

        $validator->after(function ($v) use ($business, $data): void {
            $deptChoice = (string) ($data['department_id'] ?? '');
            if ($deptChoice === Employee::SELECT_NEW_ROW) {
                $name = trim((string) ($data['new_department_name'] ?? ''));
                if ($name === '') {
                    $v->errors()->add('new_department_name', 'Enter the new department name.');
                } elseif (Department::where('business_id', $business->id)->where('name', $name)->exists()) {
                    $v->errors()->add('new_department_name', 'That department already exists.');
                }
            } elseif (ctype_digit($deptChoice) && ! Department::where('business_id', $business->id)->whereKey((int) $deptChoice)->exists()) {
                $v->errors()->add('department_id', 'That department does not belong to this business.');
            }

            $jtChoice = (string) ($data['job_title_id'] ?? '');
            if ($jtChoice === Employee::SELECT_NEW_ROW) {
                $name = trim((string) ($data['new_job_title_name'] ?? ''));
                if ($name === '') {
                    $v->errors()->add('new_job_title_name', 'Enter the new job title name.');
                } elseif (JobTitle::where('business_id', $business->id)->where('name', $name)->exists()) {
                    $v->errors()->add('new_job_title_name', 'That job title already exists.');
                }
            } elseif (ctype_digit($jtChoice) && ! JobTitle::where('business_id', $business->id)->whereKey((int) $jtChoice)->exists()) {
                $v->errors()->add('job_title_id', 'That job title does not belong to this business.');
            }

            if (! $v->errors()->has('basic_salary') && ! $v->errors()->has('salary')) {
                $basic    = round((float) ($data['basic_salary'] ?? 0), 2);
                $declared = round((float) ($data['salary'] ?? 0), 2);
                if (abs($declared - $basic) > 0.02) {
                    $v->errors()->add('salary', 'Monthly gross must equal basic salary (no allowances supported via API).');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $employee = DB::transaction(function () use ($business, $validated): Employee {
            $payload = $validated;

            if ((string) $payload['department_id'] === Employee::SELECT_NEW_ROW) {
                $dept = $this->departmentService->create($business, (string) $payload['new_department_name']);
                $payload['department_id'] = $dept->id;
            }

            if ((string) $payload['job_title_id'] === Employee::SELECT_NEW_ROW) {
                $jt = $this->jobTitleService->create($business, (string) $payload['new_job_title_name']);
                $payload['job_title_id'] = $jt->id;
            }

            unset($payload['new_department_name'], $payload['new_job_title_name']);
            $payload['department_id'] = (int) $payload['department_id'];
            $payload['job_title_id']  = (int) $payload['job_title_id'];

            return $this->employeeService->create($business, $payload);
        });

        $employee->load(['department', 'jobTitle']);

        return response()->json(['data' => $this->format($employee, detailed: true)], 201);
    }

    private function format(Employee $employee, bool $detailed = false): array
    {
        $typeLabel = match ($employee->employment_type) {
            'full_time' => 'Full Time',
            'part_time' => 'Part Time',
            'contract'  => 'Contract',
            default     => ucfirst((string) ($employee->employment_type ?? 'Unknown')),
        };

        $base = [
            'id'               => $employee->id,
            'name'             => $employee->full_name,
            'employee_id'      => $employee->employee_id,
            'employment_type'  => $employee->employment_type,
            'type_label'       => $typeLabel,
            'department'       => $employee->department?->name,
            'department_id'    => $employee->department_id,
            'job_title'        => $employee->jobTitle?->name,
            'job_title_id'     => $employee->job_title_id,
            'basic_salary'     => $employee->basic_salary !== null ? (float) $employee->basic_salary : null,
            'basic_salary_fmt' => $employee->basic_salary !== null
                ? number_format((float) $employee->basic_salary, 2, '.', ',')
                : null,
            'date_of_joining'  => $employee->date_of_joining?->format('Y-m-d'),
            'phone_number'     => $employee->phone_number,
        ];

        if ($detailed) {
            $base['personal_email']    = $employee->personal_email ?? null;
            $base['permanent_address'] = $employee->permanent_address ?? null;
            $base['epf_number']        = $employee->epf_number ?? null;
            $base['etf_number']        = $employee->etf_number ?? null;
        }

        return $base;
    }
}
