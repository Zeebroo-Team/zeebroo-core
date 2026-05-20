<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->foreignId('bank_id')->after('bank_account_holder_name')->constrained('banks')->restrictOnDelete();
        });

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropColumn('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->string('bank_name')->after('bank_account_holder_name');
        });

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_id');
        });
    }
};
