<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desktop offline mode: every POS action queued while offline (returns,
 * voids, cash drawer, end of day, …) is uploaded with an Idempotency-Key
 * header. The first successful answer is kept here so a retried upload gets
 * that same answer back instead of doing the action a second time.
 * See Modules\Pos\Http\Middleware\PosIdempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_idempotency_keys')) {
            return;
        }

        Schema::create('pos_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            // sha256 of business id + key: one key can't collide across businesses
            $table->char('scope', 64)->unique();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status_code');
            $table->longText('response');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_idempotency_keys');
    }
};
