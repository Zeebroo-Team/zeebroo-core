<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('design_studio_designs')) {
            return;
        }

        Schema::table('design_studio_designs', function (Blueprint $table) {
            if (! Schema::hasColumn('design_studio_designs', 'proposal_group')) {
                $table->char('proposal_group', 36)->nullable()->after('type')->index();
            }
            if (! Schema::hasColumn('design_studio_designs', 'proposal_sort')) {
                $table->tinyInteger('proposal_sort')->nullable()->after('proposal_group');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('design_studio_designs')) {
            return;
        }

        Schema::table('design_studio_designs', function (Blueprint $table) {
            $table->dropColumn(['proposal_group', 'proposal_sort']);
        });
    }
};
