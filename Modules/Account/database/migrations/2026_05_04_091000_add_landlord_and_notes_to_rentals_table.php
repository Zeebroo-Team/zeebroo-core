<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->foreignId('address_book_id')
                ->nullable()
                ->after('business_id')
                ->constrained('address_books')
                ->nullOnDelete();
            $table->unsignedSmallInteger('remind_before_days')->nullable()->after('recurring_type');
            $table->text('notes')->nullable()->after('remind_before_days');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('address_book_id');
            $table->dropColumn(['remind_before_days', 'notes']);
        });
    }
};
