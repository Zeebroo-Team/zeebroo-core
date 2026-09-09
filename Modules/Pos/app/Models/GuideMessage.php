<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $table = 'pos_guide_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'is_voice',
    ];

    protected $casts = [
        'is_voice' => 'bool',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(GuideConversation::class, 'conversation_id');
    }
}
