<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->string('color', 20)->default('#ca8a04')->after('description');
        });

        // Null FK references before replacing seed data
        DB::table('documents')->update(['document_category_id' => null]);
        DB::table('document_categories')->delete();

        DB::table('document_categories')->insert([
            [
                'name'        => 'Get Started',
                'slug'        => 'get-started',
                'icon'        => 'fa-rocket',
                'color'       => '#ca8a04',
                'description' => 'New to the platform? Start here with quick-start guides, installation instructions, and setup tutorials to get up and running in minutes.',
                'sort_order'  => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'User Guide',
                'slug'        => 'user-guide',
                'icon'        => 'fa-book-open',
                'color'       => '#2563eb',
                'description' => 'Comprehensive documentation covering all features, workflows, and best practices for getting the most out of the platform every day.',
                'sort_order'  => 2,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'Developer Documentation',
                'slug'        => 'developer-documentation',
                'icon'        => 'fa-code',
                'color'       => '#7c3aed',
                'description' => 'Technical references, REST API docs, webhook integrations, and SDK guides for developers building on or extending the platform.',
                'sort_order'  => 3,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'FAQ',
                'slug'        => 'faq',
                'icon'        => 'fa-circle-question',
                'color'       => '#059669',
                'description' => 'Quick answers to the most frequently asked questions. Find solutions, clarifications, and helpful tips without reading full guides.',
                'sort_order'  => 4,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
