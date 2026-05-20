<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_job_titles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'name']);
        });

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->foreignId('job_title_id')->nullable()->after('employee_id')->constrained('hr_job_titles')->restrictOnDelete();
        });

        $now = now();

        if (Schema::hasColumn('hr_employees', 'job_title')) {
            foreach (DB::table('hr_employees')->select(['id', 'business_id', 'job_title'])->get() as $row) {
                $name = trim((string) ($row->job_title ?? ''));
                if ($name === '') {
                    $name = 'Unspecified';
                }

                $jobTitleId = DB::table('hr_job_titles')
                    ->where('business_id', $row->business_id)
                    ->where('name', $name)
                    ->value('id');

                if ($jobTitleId === null) {
                    $jobTitleId = DB::table('hr_job_titles')->insertGetId([
                        'business_id' => $row->business_id,
                        'name' => $name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('hr_employees')->where('id', $row->id)->update([
                    'job_title_id' => $jobTitleId,
                ]);
            }

            Schema::table('hr_employees', function (Blueprint $table): void {
                $table->dropColumn('job_title');
            });
        }

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->foreignId('job_title_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->string('job_title')->nullable()->after('employee_id');
        });

        foreach (DB::table('hr_employees')->whereNotNull('job_title_id')->get(['id', 'job_title_id']) as $employee) {
            $name = DB::table('hr_job_titles')->where('id', $employee->job_title_id)->value('name')
                ?? '';

            DB::table('hr_employees')->where('id', $employee->id)->update([
                'job_title' => (string) $name,
            ]);
        }

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_title_id');
        });

        Schema::dropIfExists('hr_job_titles');
    }
};
