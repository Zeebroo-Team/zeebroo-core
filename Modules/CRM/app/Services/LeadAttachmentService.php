<?php

namespace Modules\CRM\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAttachment;
use Modules\FileManager\Services\FileManagerService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files on a lead. Each is stored through the FileManager in "CRM/Leads/<lead id>" on the
 * default (private) disk and marked as CRM-managed, so it never gets a public URL and the file
 * manager does not serve it — only the CRM download route does, after LeadPolicy::canView.
 */
class LeadAttachmentService
{
    /** Allowed extensions. No SVG: it can carry script and images are previewed inline. */
    public const ALLOWED_MIMES = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'zip',
    ];

    /** Image types shown as a preview. */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private readonly FileManagerService $files) {}

    public static function maxKb(): int
    {
        return (int) config('crm.attachments.max_kb', 10240);
    }

    /** Validation rules for one uploaded file. */
    public static function fileRules(): array
    {
        return ['file', 'max:' . self::maxKb(), 'mimes:' . implode(',', self::ALLOWED_MIMES)];
    }

    /** @return Collection<int, LeadAttachment> newest first */
    public function forLead(Lead $lead): Collection
    {
        return $lead->attachments()->with(['file', 'uploadedBy:id,name'])->get();
    }

    public function attachmentForLead(Lead $lead, int $attachmentId): LeadAttachment
    {
        return LeadAttachment::query()
            ->where('business_id', $lead->business_id)
            ->where('lead_id', $lead->id)
            ->with('file')
            ->findOrFail($attachmentId);
    }

    public function upload(Lead $lead, UploadedFile $file, ?int $userId): LeadAttachment
    {
        $lead->loadMissing('business');
        $folder = $this->files->ensureFolderPath($lead->business, ['CRM', 'Leads', (string) $lead->id]);

        $stored = $this->files->storeFile(
            $lead->business,
            $file,
            $folder?->id,
            $userId,
            'Lead #' . $lead->id . ' — ' . mb_substr($lead->name, 0, 150),
            self::disk(),
            'crm',
        );

        return LeadAttachment::create([
            'business_id'          => $lead->business_id,
            'lead_id'              => $lead->id,
            'file_manager_file_id' => $stored->id,
            'uploaded_by'          => $userId,
            'is_image'             => in_array($stored->mime_type, self::IMAGE_MIMES, true),
        ])->setRelation('file', $stored);
    }

    /**
     * The file as a download, or inline (images only, for previews). Callers check that the user
     * may view the lead first.
     */
    public function download(LeadAttachment $attachment, bool $inline = false): StreamedResponse
    {
        $file = $attachment->file;
        abort_if($file === null || ! Storage::disk($file->diskName())->exists($file->stored_path), 404);

        $disk = Storage::disk($file->diskName());
        $headers = ['Content-Type' => $file->mime_type ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'];

        return $inline && $attachment->is_image
            ? $disk->response($file->stored_path, $file->original_filename, $headers)
            : $disk->download($file->stored_path, $file->original_filename, $headers);
    }

    public function delete(LeadAttachment $attachment): void
    {
        DB::transaction(function () use ($attachment) {
            $file = $attachment->file;
            $attachment->delete();
            if ($file !== null) {
                $this->files->deleteFile($file);
            }
        });
    }

    /** Remove every file of a lead that is about to be deleted. */
    public function deleteAllFor(Lead $lead): void
    {
        $lead->attachments()->with('file')->get()->each(fn (LeadAttachment $a) => $this->delete($a));
    }

    /** @return array<string, mixed> */
    public function present(LeadAttachment $attachment, bool $canDelete): array
    {
        $file = $attachment->file;

        return [
            'id'          => $attachment->id,
            'name'        => $file?->original_filename ?? 'Missing file',
            'mime_type'   => $file?->mime_type,
            'size_bytes'  => $file?->size_bytes,
            'size'        => $file?->humanSize(),
            'is_image'    => (bool) $attachment->is_image,
            'uploaded_by' => $attachment->uploaded_by ? ['id' => $attachment->uploaded_by, 'name' => $attachment->uploadedBy?->name ?? 'Deleted user'] : null,
            'created_at'  => $attachment->created_at?->toIso8601String(),
            'can_delete'  => $canDelete,
        ];
    }

    /** Lead files go on the default disk unless that is the public one. */
    private static function disk(): string
    {
        $default = (string) config('crm.attachments.disk', config('filesystems.default', 'local'));

        return $default === 'public' ? 'local' : $default;
    }
}
