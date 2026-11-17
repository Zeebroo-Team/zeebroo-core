<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('subject', 200);
            $table->longText('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('admin_email_templates')->nullOnDelete();
            $table->string('subject', 200);
            $table->longText('body');
            $table->string('status', 20)->default('sending');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('admin_email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('admin_email_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('marketing_opt_out_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('marketing_opt_out_at');
        });

        Schema::dropIfExists('admin_email_campaign_recipients');
        Schema::dropIfExists('admin_email_campaigns');
        Schema::dropIfExists('admin_email_templates');
    }
};
