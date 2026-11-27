<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An uploaded copy of an affiliate's ID card (front/back) or passport. Stored on the private disk. */
class AffiliateDocument extends Model
{
    public const NIC_FRONT = 'nic_front';

    public const NIC_BACK = 'nic_back';

    public const PASSPORT = 'passport';

    /** Document slots for each ID type, with their labels. */
    public const SLOTS = [
        AffiliateProfile::ID_NIC => [
            self::NIC_FRONT => 'ID card - front',
            self::NIC_BACK => 'ID card - back',
        ],
        AffiliateProfile::ID_PASSPORT => [
            self::PASSPORT => 'Passport photo page',
        ],
    ];

    protected $fillable = ['user_id', 'type', 'path', 'original_name', 'mime', 'size_bytes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function types(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::SLOTS)));
    }

    public static function labelFor(string $type): string
    {
        foreach (self::SLOTS as $slots) {
            if (isset($slots[$type])) {
                return $slots[$type];
            }
        }

        return $type;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function sizeLabel(): string
    {
        return $this->size_bytes >= 1024 * 1024
            ? number_format($this->size_bytes / (1024 * 1024), 1).' MB'
            : max(1, (int) round($this->size_bytes / 1024)).' KB';
    }
}
