<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bm_jobs', function (Blueprint $table) {
            // e.g. "26/DIM/001"  – year / client short-code / sequential per client
            $table->string('job_ref', 30)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('bm_jobs', function (Blueprint $table) {
            $table->dropColumn('job_ref');
        });
    }
};
