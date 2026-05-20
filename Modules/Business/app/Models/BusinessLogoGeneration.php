<?php

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessLogoGeneration extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'business_id',
        'status',
        'company_category',
        'logo_style',
        'background_theme',
        'custom_prompt',
        'logo_path',
        'error_message',
    ];

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
