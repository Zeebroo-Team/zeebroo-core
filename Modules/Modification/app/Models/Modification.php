<?php

declare(strict_types=1);

namespace Modules\Modification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Account\Models\Bill;
use Modules\Business\Models\Business;

class Modification extends Model
{
    protected $table = 'modifications';

    public const PROPERTY_WORK_TYPE_REPAIR = 'repair';
    public const PROPERTY_WORK_TYPE_MODIFICATION = 'modification';
    public const PROPERTY_WORK_TYPE_OTHER = 'other';

    protected $fillable = [
        'business_id',
        'created_by_user_id',
        'name',
        'assignment_type',
        'assignment_reference',
        'property_work_type',
        'property_work_type_other',
        'estimated_cost',
        'duration',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'modification_id');
    }

    /** @return array<string, string> */
    public static function renovationTypeLabels(): array
    {
        return [
            'painting' => __('Painting'),
            'plumbing' => __('Plumbing'),
            'electrical' => __('Electrical'),
            'flooring' => __('Flooring'),
            'interior' => __('Interior'),
            'exterior' => __('Exterior'),
            'general' => __('General renovation'),
        ];
    }

    /** @return array<string, string> */
    public static function propertyWorkTypeLabels(): array
    {
        return [
            self::PROPERTY_WORK_TYPE_REPAIR => __('Repair'),
            self::PROPERTY_WORK_TYPE_MODIFICATION => __('Modification'),
            self::PROPERTY_WORK_TYPE_OTHER => __('Other'),
        ];
    }

    /**
     * @param  array<string, string>  $propertyLookupById  keyed by property id string, "Name · type"
     */
    public static function displayAssignmentReference(?string $assignmentType, mixed $assignmentReference, array $propertyLookupById = []): ?string
    {
        $aref = $assignmentReference;
        if ($aref === null || $aref === '') {
            return null;
        }
        $type = (string) ($assignmentType ?? '');
        if ($type === 'renovation') {
            return static::renovationTypeLabels()[(string) $aref] ?? (string) $aref;
        }
        if ($type === 'property' && ctype_digit((string) $aref)) {
            return $propertyLookupById[(string) $aref] ?? (string) $aref;
        }

        return (string) $aref;
    }
}
