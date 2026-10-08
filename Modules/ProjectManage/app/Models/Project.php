<?php

namespace Modules\ProjectManage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function customStatuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class)->orderBy('sort_order')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /** Users on the project team — the only users its tasks can be assigned to. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'pm_project_members')
            ->withPivot('added_by')
            ->withTimestamps();
    }

    public function hasMember(int $userId): bool
    {
        return $this->members()->where('users.id', $userId)->exists();
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
     * Task counts by completion status (incomplete / complete / cancelled) and by stage.
     * "done" = completed tasks and "open" = incomplete ones, whatever stage they sit in;
     * cancelled tasks are neither, and are left out of progress (see progressPct()).
     *
     * @return array{todo:int,in_progress:int,review:int,done_stage:int,stages:array<string,int>,incomplete:int,complete:int,cancelled:int,open:int,done:int,total:int}
     */
    public function taskStats(): array
    {
        $rows = $this->tasks()
            ->selectRaw('status, completion_status, count(*) as cnt')
            ->groupBy('status', 'completion_status')
            ->get();

        $stages     = $rows->groupBy('status')->map(fn ($g) => (int) $g->sum('cnt'))->all();
        $completion = $rows->groupBy(fn ($r) => $r->completion_status ?: Task::COMPLETION_INCOMPLETE)
            ->map(fn ($g) => (int) $g->sum('cnt'));

        $incomplete = (int) ($completion[Task::COMPLETION_INCOMPLETE] ?? 0);
        $complete   = (int) ($completion[Task::COMPLETION_COMPLETE] ?? 0);

        return [
            // Stage (board column) counts — built-ins by name, every stage in "stages".
            'todo'        => (int) ($stages[Task::STATUS_TODO] ?? 0),
            'in_progress' => (int) ($stages[Task::STATUS_IN_PROGRESS] ?? 0),
            'review'      => (int) ($stages[Task::STATUS_REVIEW] ?? 0),
            'done_stage'  => (int) ($stages[Task::STATUS_DONE] ?? 0),
            'stages'      => $stages,
            // Completion status counts.
            'incomplete'  => $incomplete,
            'complete'    => $complete,
            'cancelled'   => (int) ($completion[Task::COMPLETION_CANCELLED] ?? 0),
            'open'        => $incomplete,
            'done'        => $complete,
            'total'       => (int) $rows->sum('cnt'),
        ];
    }

    /** Completed share of the tasks that still count (cancelled ones are left out), 0–100. */
    public static function progressPct(array $stats): int
    {
        $base = ($stats['total'] ?? 0) - ($stats['cancelled'] ?? 0);

        return $base > 0 ? (int) round(($stats['done'] ?? 0) / $base * 100) : 0;
    }
}
