<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A group is one "kind" of gift card (e.g. "Birthday Gift Card") that can
        // hold many individual cards, each with its own code and balance.
        Schema::create('pos_gift_card_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 191);
            $table->decimal('initial_value', 12, 2); // default face value for new cards
            $table->date('valid_from')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
        });

        Schema::table('pos_gift_cards', function (Blueprint $table): void {
            $table->foreignId('pos_gift_card_group_id')->nullable()->after('business_id')
                  ->constrained('pos_gift_card_groups')->cascadeOnDelete();
        });

        // Cards created before groups existed each become their own group.
        foreach (DB::table('pos_gift_cards')->whereNull('pos_gift_card_group_id')->get() as $card) {
            $groupId = DB::table('pos_gift_card_groups')->insertGetId([
                'business_id'   => $card->business_id,
                'name'          => $card->name,
                'initial_value' => $card->initial_value,
                'valid_from'    => $card->valid_from,
                'expires_at'    => $card->expires_at,
                'is_active'     => $card->is_active,
                'notes'         => $card->notes,
                'created_by'    => $card->created_by,
                'created_at'    => $card->created_at,
                'updated_at'    => $card->updated_at,
            ]);
            DB::table('pos_gift_cards')->where('id', $card->id)->update(['pos_gift_card_group_id' => $groupId]);
        }
    }

    public function down(): void
    {
        Schema::table('pos_gift_cards', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pos_gift_card_group_id');
        });
        Schema::dropIfExists('pos_gift_card_groups');
    }
};
