<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for leads: who did what, when, from where. Names of the lead,
 * user and stages are snapshotted so history stays readable after any of them is renamed
 * or deleted — lead_id deliberately has no FK so a deleted lead's history (including the
 * "deleted" entry itself) is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('lead_id')->index();
            $table->string('lead_name')->nullable();
            $table->string('event', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('source', 32)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('from_stage_id')->nullable();
            $table->string('from_stage_name')->nullable();
            $table->unsignedBigInteger('to_stage_id')->nullable();
            $table->string('to_stage_name')->nullable();
            $table->unsignedBigInteger('stage_duration_seconds')->nullable();
            $table->json('changes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'created_at']);
            $table->index(['lead_id', 'created_at']);
            $table->index(['business_id', 'user_id']);
        });

        $this->backfillFromStageLogs();
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_audit_logs');
    }

    /** Carry the existing stage-move history over so the timeline isn't empty on day one. */
    private function backfillFromStageLogs(): void
    {
        if (! Schema::hasTable('crm_lead_stage_logs')) {
            return;
        }

        $lastEntered = [];

        DB::table('crm_lead_stage_logs as l')
            ->join('crm_leads as lead', 'lead.id', '=', 'l.lead_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.changed_by')
            ->leftJoin('crm_lead_stages as fs', 'fs.id', '=', 'l.from_stage_id')
            ->leftJoin('crm_lead_stages as ts', 'ts.id', '=', 'l.to_stage_id')
            ->orderBy('l.id')
            ->select([
                'l.lead_id', 'l.project_id', 'l.from_stage_id', 'l.to_stage_id', 'l.changed_by', 'l.created_at',
                'lead.business_id', 'lead.name as lead_name', 'lead.source as lead_source',
                'u.name as user_name', 'fs.name as from_stage_name', 'ts.name as to_stage_name',
            ])
            ->chunk(500, function ($rows) use (&$lastEntered) {
                $insert = [];

                foreach ($rows as $row) {
                    $created  = $row->from_stage_id === null;
                    $duration = null;
                    if (! $created && isset($lastEntered[$row->lead_id])) {
                        $duration = max(0, strtotime($row->created_at) - strtotime($lastEntered[$row->lead_id]));
                    }
                    $lastEntered[$row->lead_id] = $row->created_at;

                    $insert[] = [
                        'business_id'            => $row->business_id,
                        'project_id'             => $row->project_id,
                        'lead_id'                => $row->lead_id,
                        'lead_name'              => $row->lead_name,
                        'event'                  => $created ? 'created' : 'stage_changed',
                        'user_id'                => $row->changed_by,
                        'user_name'              => $row->user_name,
                        'source'                 => $row->changed_by ? null : ($created ? $row->lead_source : 'system'),
                        'from_stage_id'          => $row->from_stage_id,
                        'from_stage_name'        => $row->from_stage_name,
                        'to_stage_id'            => $row->to_stage_id,
                        'to_stage_name'          => $row->to_stage_name,
                        'stage_duration_seconds' => $duration,
                        'created_at'             => $row->created_at,
                    ];
                }

                DB::table('crm_lead_audit_logs')->insert($insert);
            });
    }
};
