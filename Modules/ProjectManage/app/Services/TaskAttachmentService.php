<?php

namespace Modules\ProjectManage\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\ProjectManage\Models\Task;
use Modules\ProjectManage\Models\TaskAttachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentService
{
    /** Allowed extensions: PDFs, images, office documents, text and archives. */
    const EXTENSIONS = 'pdf,jpg,jpeg,png,gif,webp,bmp,svg,doc,docx,xls,xlsx,csv,ppt,pptx,txt,rtf,odt,ods,zip,rar,7z';

    const MAX_KB = 20480; // 20 MB per file

    /** Validation rules for an upload (shared by the Projects + My Projects endpoints). */
    public static function uploadRules(): array
    {
        return [
            'files'   => 'required|array|min:1|max:10',
            'files.*' => 'file|max:' . self::MAX_KB . '|extensions:' . self::EXTENSIONS,
        ];
    }

    public static function uploadMessages(): array
    {
        return [
            'files.*.max'        => 'Each file must be 20 MB or smaller.',
            'files.*.extensions' => 'Unsupported file type. Allowed: PDF, images, Word/Excel/PowerPoint, text and zip files.',
        ];
    }

    public function listForTask(Task $task): Collection
    {
        return TaskAttachment::with('user')
            ->where('task_id', $task->id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param UploadedFile[] $files
     * @return Collection<int, TaskAttachment>
     */
    public function store(Task $task, array $files, ?int $userId): Collection
    {
        $dir = $this->taskDir($task);

        return collect($files)->map(function (UploadedFile $file) use ($task, $dir, $userId) {
            $path = $file->store($dir, TaskAttachment::DISK);

            return TaskAttachment::create([
                'task_id'       => $task->id,
                'user_id'       => $userId,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'stored_path'   => $path,
                'mime_type'     => $file->getMimeType() ?: $file->getClientMimeType(),
                'size_bytes'    => $file->getSize() ?: null,
            ])->load('user');
        });
    }

    public function download(TaskAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk(TaskAttachment::DISK);
        abort_unless($disk->exists($attachment->stored_path), 404, 'The file is missing on the server.');

        return $disk->download($attachment->stored_path, $attachment->original_name);
    }

    public function delete(TaskAttachment $attachment): void
    {
        Storage::disk(TaskAttachment::DISK)->delete($attachment->stored_path);
        $attachment->delete();
    }

    /** Removes every stored file of a task (rows cascade with the task itself). */
    public function deleteAllForTask(Task $task): void
    {
        Storage::disk(TaskAttachment::DISK)->deleteDirectory($this->taskDir($task));
    }

    public function fmt(TaskAttachment $a): array
    {
        return [
            'id'          => $a->id,
            'task_id'     => $a->task_id,
            'name'        => $a->original_name,
            'mime_type'   => $a->mime_type,
            'size_bytes'  => $a->size_bytes,
            'user_id'     => $a->user_id,
            'uploaded_by' => $a->user?->name ?? 'System',
            // My Projects only lets assignees delete their own uploads.
            'is_mine'     => $a->user_id !== null && (int) $a->user_id === (int) auth()->id(),
            'created_at'  => $a->created_at?->toDateTimeString(),
        ];
    }

    private function taskDir(Task $task): string
    {
        $task->loadMissing('project');

        return 'pm-task-attachments/' . $task->project->business_id . '/' . $task->project_id . '/' . $task->id;
    }
}
