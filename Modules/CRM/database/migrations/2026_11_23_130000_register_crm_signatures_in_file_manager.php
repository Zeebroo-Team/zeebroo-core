<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Signatures saved before they went through the file manager: list each one in the
     * business's admin-only "CRM / Signatures" folder (the file stays where it is).
     */
    public function up(): void
    {
        if (! Schema::hasTable('file_manager_files') || ! Schema::hasColumn('crm_user_preferences', 'signature_path')) {
            return;
        }

        $rows = DB::table('crm_user_preferences')->whereNotNull('signature_path')->get(['business_id', 'user_id', 'signature_path']);
        foreach ($rows as $row) {
            $exists = DB::table('file_manager_files')->where('business_id', $row->business_id)->where('stored_path', $row->signature_path)->exists();
            if ($exists || ! Storage::disk('local')->exists($row->signature_path)) {
                continue;
            }

            $folderId = $this->folderId((int) $row->business_id, ['CRM', 'Signatures']);
            $name     = (string) (DB::table('users')->where('id', $row->user_id)->value('name') ?? ('User #' . $row->user_id));
            $ext      = strtolower(pathinfo($row->signature_path, PATHINFO_EXTENSION));

            DB::table('file_manager_files')->insert([
                'business_id'         => $row->business_id,
                'folder_id'           => $folderId,
                'uploaded_by_user_id' => $row->user_id,
                'original_filename'   => 'signature.' . ($ext ?: 'png'),
                'stored_path'         => $row->signature_path,
                'disk'                => 'local',
                'managed_by'          => 'crm-signature',
                'mime_type'           => in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png',
                'size_bytes'          => Storage::disk('local')->size($row->signature_path) ?: null,
                'notes'               => 'Signature of ' . mb_substr($name, 0, 150),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The rows stay: removing them would leave the signatures unlisted for admins.
    }

    /** @param list<string> $names */
    private function folderId(int $businessId, array $names): int
    {
        $parentId = null;
        foreach ($names as $name) {
            $id = DB::table('file_manager_folders')->where('business_id', $businessId)->where('parent_id', $parentId)->where('name', $name)->value('id');
            $parentId = $id ?? DB::table('file_manager_folders')->insertGetId([
                'business_id' => $businessId, 'parent_id' => $parentId, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return (int) $parentId;
    }
};
