<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

/** A user's CRM preferences in one business. No row = the defaults. */
class UserPreference extends Model
{
    protected $table = 'crm_user_preferences';

    protected $fillable = ['business_id', 'user_id', 'email_notifications'];

    protected $casts = ['email_notifications' => 'boolean'];
}
