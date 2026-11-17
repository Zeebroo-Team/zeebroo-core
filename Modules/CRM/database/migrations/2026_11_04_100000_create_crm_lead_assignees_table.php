<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who works a lead: one or more staff, one of them primary. crm_leads.assigned_to stays
        // in sync with the primary assignee for older code paths (stage mail, reports, …).
        Schema::create('crm_lead_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            $table->unique(['lead_id', 'user_id']);
            $table->index(['business_id', 'user_id']);
        });

        $this->backfillFromAssignedTo();
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_assignees');
    }

    /** Every lead assigned so far keeps its assignee, as the primary one. */
    private function backfillFromAssignedTo(): void
    {
        DB::table('crm_leads')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->select(['id', 'business_id', 'assigned_to', 'created_at', 'updated_at'])
            ->chunk(500, function ($rows) {
                DB::table('crm_lead_assignees')->insert($rows->map(fn ($row) => [
                    'business_id' => $row->business_id,
                    'lead_id'     => $row->id,
                    'user_id'     => $row->assigned_to,
                    'is_primary'  => true,
                    'assigned_by' => null,
                    'assigned_at' => $row->updated_at ?? $row->created_at ?? now(),
                ])->all());
            });
    }
};
