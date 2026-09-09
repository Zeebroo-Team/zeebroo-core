<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\FileManager\Models\FileManagerFile;
use Modules\Pos\Models\Customer;

class Project extends Model
{
    protected $table = 'pm_projects';

    const STATUS_ACTIVE    = 'active';
    const STATUS_ON_HOLD   = 'on_hold';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ARCHIVED  = 'archived';

    const PRIORITY_LOW    = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH   = 'high';

    const TYPE_IN_HOUSE = 'in_house';
    const TYPE_CUSTOMER = 'customer';

    const ASSIGNMENT_NONE         = 'none';
    const ASSIGNMENT_BRANCH       = 'branch';
    const ASSIGNMENT_DEPARTMENT   = 'department';
    const ASSIGNMENT_PROPERTY     = 'property';
    const ASSIGNMENT_EMPLOYEE     = 'employee';
    const ASSIGNMENT_MODIFICATION = 'modification';
    const ASSIGNMENT_RENTAL       = 'rental';
    const ASSIGNMENT_OTHER        = 'other';

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'status',
        'priority',
        'color',
        'start_date',
        'due_date',
        'budget',
        'client_name',
        'project_type',
        'customer_id',
        'assignment_type',
        'branch_id',
        'department_id',
        'property_id',
        'employee_id',
        'modification_id',
        'rental_id',
        'assignment_reference',
        'file_manager_file_id',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date'   => 'date',
        'budget'     => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\Modules\HRManagement\Models\Department::class, 'department_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(\Modules\Account\Models\Property::class, 'property_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\HRManagement\Models\Employee::class, 'employee_id');
    }

    public function modification(): BelongsTo
    {
        return $this->belongsTo(\Modules\Modification\Models\Modification::class, 'modification_id');
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(\Modules\Account\Models\Rental::class, 'rental_id');
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(FileManagerFile::class, 'file_manager_file_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function assignedUsers(): HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\User::class,
            Task::class,
            'project_id',
            'id',
            'id',
            'assigned_to'
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * @return array{todo:int,in_progress:int,review:int,done:int,total:int}
     */
    public function taskStats(): array
    {
        $counts = $this->tasks()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'todo'        => (int) ($counts[Task::STATUS_TODO] ?? 0),
            'in_progress' => (int) ($counts[Task::STATUS_IN_PROGRESS] ?? 0),
            'review'      => (int) ($counts[Task::STATUS_REVIEW] ?? 0),
            'done'        => (int) ($counts[Task::STATUS_DONE] ?? 0),
            'total'       => (int) $counts->sum(),
        ];
    }
}
