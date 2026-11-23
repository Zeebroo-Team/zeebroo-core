<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A user's CRM preferences in one business, and their CRM profile (display name, designation,
 * phone, signature — printed as "Prepared by" on quotations; CrmProfileService). No row = the defaults.
 */
class UserPreference extends Model
{
    protected $table = 'crm_user_preferences';

    protected $fillable = ['business_id', 'user_id', 'email_notifications', 'display_name', 'designation', 'phone', 'signature_path'];

    protected $casts = ['email_notifications' => 'boolean'];
}
