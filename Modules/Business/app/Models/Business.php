<?php

namespace Modules\Business\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Rental;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Models\FileManagerFolder;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductBrand;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\ProductBarcodeSheet;
use Modules\Product\Models\ProductDiscount;
use Modules\Product\Models\ProductUnit;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\Supplier;
use Modules\HRManagement\Models\AllowanceType;
use Modules\HRManagement\Models\AttendanceRecord;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\HrBusinessHoliday;
use Modules\HRManagement\Models\HrComplaint;
use Modules\HRManagement\Models\JobTitle;
use Modules\HRManagement\Models\LeaveRequest;
use Modules\HRManagement\Models\PayrollCustomTemplate;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\HRManagement\Models\PayrollRuleSet;
use Modules\Modification\Models\Modification;
use Modules\Settings\Concerns\HasSettings;

class Business extends Model
{
    use HasSettings;

    /** Members (non-owner users assigned to this business). */
    public function members(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    /** Whether a given user can access this business (owner or active member). */
    public static function canAccess(User $user, self $business): bool
    {
        if ((int) $business->user_id === (int) $user->id) {
            return true;
        }

        return $business->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    /** Active member record for a user, or null if they are the owner / not a member. */
    public function memberFor(User $user): ?BusinessMember
    {
        if ((int) $this->user_id === (int) $user->id) {
            return null; // owner, not a member record
        }

        return $this->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'company_category_slug',
        'description',
        'short_description',
        'brand_features',
        'google_location_resource',
        'google_location_title_cache',
        'logo_path',
        'warehouse_branch_intro_acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_branch_intro_acknowledged_at' => 'datetime',
            'google_location_linked_at' => 'datetime',
            'brand_features' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Public URL for the stored logo, or null if none. */
    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return asset('storage/'.$this->logo_path);
    }

    public static function defaultLogoPlaceholderUrl(): string
    {
        return asset('images/business-logo-placeholder.svg');
    }

    /** True when the business has uploaded a custom logo file. */
    public function hasCustomLogo(): bool
    {
        return filled($this->logo_path);
    }

    /** Logo URL for UI: uploaded file or shared placeholder graphic. */
    public function displayLogoUrl(): string
    {
        return $this->logoUrl() ?? self::defaultLogoPlaceholderUrl();
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function modifications(): HasMany
    {
        return $this->hasMany(Modification::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class)->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('name');
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class)->orderBy('sort_order')->orderBy('name');
    }

    public function productBrands(): HasMany
    {
        return $this->hasMany(ProductBrand::class)->orderBy('name');
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('sort_order')->orderBy('name');
    }

    public function productBarcodeSheets(): HasMany
    {
        return $this->hasMany(ProductBarcodeSheet::class)->orderByDesc('created_at');
    }

    public function productDiscounts(): HasMany
    {
        return $this->hasMany(ProductDiscount::class)->orderByDesc('created_at');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class)->orderBy('name');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(\Modules\Pos\Models\Sale::class);
    }

    public function goodsReceiveNotes(): HasMany
    {
        return $this->hasMany(GoodsReceiveNote::class);
    }

    public function chequePayments(): HasMany
    {
        return $this->hasMany(\Modules\Purchase\Models\ChequePayment::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(\Modules\Sales\Models\Quotation::class);
    }

    public function fileManagerFolders(): HasMany
    {
        return $this->hasMany(FileManagerFolder::class)->orderBy('name');
    }

    public function fileManagerFiles(): HasMany
    {
        return $this->hasMany(FileManagerFile::class)->orderByDesc('created_at');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class)->orderBy('full_name')->orderBy('id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class)->orderBy('name')->orderBy('id');
    }

    public function jobTitles(): HasMany
    {
        return $this->hasMany(JobTitle::class)->orderBy('name')->orderBy('id');
    }

    public function allowanceTypes(): HasMany
    {
        return $this->hasMany(AllowanceType::class)->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->orderByDesc('created_at');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class)->orderByDesc('work_date')->orderByDesc('id');
    }

    /** Grievances / HR issues logged against this business (internal inbox). */
    public function hrComplaints(): HasMany
    {
        return $this->hasMany(HrComplaint::class)->orderByDesc('created_at');
    }

    /** Company-wide public holidays for calendars and payroll (reference). */
    public function hrHolidays(): HasMany
    {
        return $this->hasMany(HrBusinessHoliday::class)->orderBy('holiday_date')->orderBy('id');
    }

    public function payrollRuleSets(): HasMany
    {
        return $this->hasMany(PayrollRuleSet::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    /** User-imported payroll preset definitions (JSON), scoped to this business. */
    public function payrollCustomTemplates(): HasMany
    {
        return $this->hasMany(PayrollCustomTemplate::class);
    }

    public function payrollCycles(): HasMany
    {
        return $this->hasMany(PayrollCycle::class)->orderByDesc('year')->orderByDesc('month')->orderByDesc('id');
    }

    public static function allForNavbar(?User $user): Collection
    {
        if (! $user) {
            return new Collection([]);
        }

        $owned = static::query()->where('user_id', $user->id)->pluck('id');

        $memberId = \Modules\Business\Models\BusinessMember::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('business_id');

        $ids = $owned->merge($memberId)->unique()->values();

        return static::query()->whereIn('id', $ids)->latest()->get();
    }

    /** Multi warehouse / branch mode enabled in business settings (default off). */
    public function multiWarehouseBranchEnabled(): bool
    {
        return (bool) get_settings('business.multi_warehouse_branch', false, $this);
    }

    /**
     * Business currently selected in the navbar (session), or latest as fallback.
     */
    public static function currentForNavbar(?User $user): ?static
    {
        if (! $user) {
            return null;
        }

        $selectedId = (int) session('selected_business_id');

        if ($selectedId !== 0) {
            $all  = static::allForNavbar($user);
            $match = $all->firstWhere('id', $selectedId);
            if ($match) {
                return $match;
            }
            session()->forget('selected_business_id');
        }

        $latest = static::allForNavbar($user)->first();
        if ($latest) {
            session(['selected_business_id' => $latest->id]);

            return $latest;
        }

        session()->forget('selected_business_id');

        return null;
    }
}
