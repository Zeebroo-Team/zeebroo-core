<?php

namespace Modules\FileManager\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\Business\Models\Business;

class FileManagerFile extends Model
{
    protected $fillable = [
        'business_id',
        'folder_id',
        'uploaded_by_user_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'size_bytes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(FileManagerFolder::class, 'folder_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function publicUrl(): string
    {
        return Storage::disk('public')->url($this->stored_path);
    }

    public function humanSize(): string
    {
        $bytes = (int) ($this->size_bytes ?? 0);
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
