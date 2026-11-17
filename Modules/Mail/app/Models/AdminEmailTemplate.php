<?php

namespace Modules\Mail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-wide marketing template authored by an admin (not tied to a business).
 */
class AdminEmailTemplate extends Model
{
    protected $table = 'admin_email_templates';

    protected $fillable = [
        'name',
        'subject',
        'body',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdminEmailCampaign::class, 'template_id');
    }
}
