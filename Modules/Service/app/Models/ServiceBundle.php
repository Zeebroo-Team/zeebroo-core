<?php

namespace Modules\Service\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Business\Models\Business;

class ServiceBundle extends Model
{
    protected $table = 'service_bundles';

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceItem::class,
            'service_bundle_items',
            'service_bundle_id',
            'service_item_id',
        )->withPivot('qty')->withTimestamps();
    }

    /** Sum of individual service prices × qty (null if any price is missing). */
    public function totalIndividualPrice(): ?float
    {
        $total = 0.0;
        foreach ($this->services as $svc) {
            if ($svc->price === null) return null;
            $total += (float) $svc->price * (int) ($svc->pivot->qty ?? 1);
        }
        return $total;
    }
}
