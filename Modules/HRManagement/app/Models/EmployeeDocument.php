<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class EmployeeDocument extends Model
{
    protected $table = 'hr_employee_documents';

    public const CATEGORY_CERTIFICATION = 'certification';

    public const CATEGORY_PERSONAL = 'personal';

    public const CATEGORY_APPLICATION = 'application';

    public const CATEGORY_CONTRACT = 'contract';

    public const CATEGORY_IDENTIFICATION = 'identification';

    public const CATEGORY_ONBOARDING = 'onboarding';

    public const CATEGORY_MEDICAL = 'medical';

    public const CATEGORY_PAYROLL = 'payroll';

    public const CATEGORY_TAX_STATUTORY = 'tax_statutory';

    public const CATEGORY_DISCIPLINARY = 'disciplinary';

    public const CATEGORY_RESIGNATION = 'resignation';

    public const CATEGORY_POLICY_HAND = 'policy_handbook';

    public const CATEGORY_BANKING = 'banking';

    public const CATEGORY_RESUME = 'resume';

    public const CATEGORY_INSURANCE = 'insurance';

    public const CATEGORY_TRAINING = 'training';

    public const CATEGORY_EVALUATION = 'evaluation';

    public const CATEGORY_OTHER = 'other';

    /** @var list<string> allowed `category` values */
    public const CATEGORIES = [
        self::CATEGORY_CERTIFICATION,
        self::CATEGORY_PERSONAL,
        self::CATEGORY_APPLICATION,
        self::CATEGORY_CONTRACT,
        self::CATEGORY_IDENTIFICATION,
        self::CATEGORY_ONBOARDING,
        self::CATEGORY_MEDICAL,
        self::CATEGORY_PAYROLL,
        self::CATEGORY_TAX_STATUTORY,
        self::CATEGORY_DISCIPLINARY,
        self::CATEGORY_RESIGNATION,
        self::CATEGORY_POLICY_HAND,
        self::CATEGORY_BANKING,
        self::CATEGORY_RESUME,
        self::CATEGORY_INSURANCE,
        self::CATEGORY_TRAINING,
        self::CATEGORY_EVALUATION,
        self::CATEGORY_OTHER,
    ];

    protected $fillable = [
        'business_id',
        'employee_id',
        'category',
        'original_filename',
        'stored_path',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            self::CATEGORY_CERTIFICATION => __('Certification'),
            self::CATEGORY_PERSONAL => __('Personal files'),
            self::CATEGORY_APPLICATION => __('Application'),
            self::CATEGORY_CONTRACT => __('Contract'),
            self::CATEGORY_IDENTIFICATION => __('Identification'),
            self::CATEGORY_ONBOARDING => __('Onboarding'),
            self::CATEGORY_MEDICAL => __('Medical'),
            self::CATEGORY_PAYROLL => __('Payroll'),
            self::CATEGORY_TAX_STATUTORY => __('Tax / statutory'),
            self::CATEGORY_DISCIPLINARY => __('Disciplinary'),
            self::CATEGORY_RESIGNATION => __('Resignation'),
            self::CATEGORY_POLICY_HAND => __('Policy / handbook / NDA'),
            self::CATEGORY_BANKING => __('Banking'),
            self::CATEGORY_RESUME => __('Resume / CV'),
            self::CATEGORY_INSURANCE => __('Insurance'),
            self::CATEGORY_TRAINING => __('Training'),
            self::CATEGORY_EVALUATION => __('Performance evaluation'),
            self::CATEGORY_OTHER => __('Other'),
            default => $category,
        };
    }
}
