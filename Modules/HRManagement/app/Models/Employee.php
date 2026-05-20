<?php

namespace Modules\HRManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Account\Models\Bank;
use Modules\Business\Models\Business;

class Employee extends Model
{
    protected $table = 'hr_employees';

    /** Sentinel for dept / job-title dropdowns matching `option value`; creates related row before saving employee. */
    public const SELECT_NEW_ROW = 'new';

    public const EMPLOYMENT_FULL_TIME = 'full_time';

    public const EMPLOYMENT_PART_TIME = 'part_time';

    public const EMPLOYMENT_CONTRACT = 'contract';

    /** @var list<string> */
    public const EMPLOYMENT_TYPES = [
        self::EMPLOYMENT_FULL_TIME,
        self::EMPLOYMENT_PART_TIME,
        self::EMPLOYMENT_CONTRACT,
    ];

    protected $fillable = [
        'user_id',
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
        'basic_salary',
        'salary',
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
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_joining' => 'date',
            'basic_salary' => 'decimal:2',
            'salary' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function employeeAllowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class)->orderBy('id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->orderByDesc('created_at');
    }

    public function hrComplaints(): HasMany
    {
        return $this->hasMany(HrComplaint::class)->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id')->orderByDesc('created_at');
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'employee_id')->orderByDesc('id');
    }

    public function biometricMappings(): HasMany
    {
        return $this->hasMany(EmployeeBiometricMapping::class, 'employee_id')->orderByDesc('id');
    }

    public function employmentTypeLabel(): string
    {
        return match ($this->employment_type) {
            self::EMPLOYMENT_FULL_TIME => 'Full-Time',
            self::EMPLOYMENT_PART_TIME => 'Part-Time',
            self::EMPLOYMENT_CONTRACT => 'Contract',
            default => $this->employment_type,
        };
    }

    /** Public URL for stored profile photo, or null. */
    public function profilePhotoUrl(): ?string
    {
        if (! filled($this->profile_photo_path)) {
            return null;
        }

        return asset('storage/'.$this->profile_photo_path);
    }

    public function hasProfilePhoto(): bool
    {
        return filled($this->profile_photo_path);
    }

    /** Two-letter initials for avatar placeholder. */
    public function avatarInitials(): string
    {
        $name = trim((string) $this->full_name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/u', $name) ?: [];
        if (count($parts) >= 2) {
            $a = mb_substr($parts[0], 0, 1);
            $b = mb_substr($parts[count($parts) - 1], 0, 1);

            return mb_strtoupper($a.$b, 'UTF-8');
        }

        return mb_strtoupper(mb_substr($parts[0], 0, min(2, mb_strlen($parts[0]))), 'UTF-8');
    }
}
