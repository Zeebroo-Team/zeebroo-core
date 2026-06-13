<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 100)->default('fa-folder-open');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('document_categories')->insert([
            ['name' => 'General',   'slug' => 'general',   'icon' => 'fa-folder-open',     'description' => 'General purpose documents and notes.',              'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Policy',    'slug' => 'policy',    'icon' => 'fa-scale-balanced',   'description' => 'Company policies and compliance documents.',        'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Procedure', 'slug' => 'procedure', 'icon' => 'fa-list-check',       'description' => 'Step-by-step procedures and workflows.',           'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Guide',     'slug' => 'guide',     'icon' => 'fa-map',              'description' => 'How-to guides and tutorials.',                     'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'FAQ',       'slug' => 'faq',       'icon' => 'fa-circle-question',  'description' => 'Frequently asked questions and answers.',          'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_categories');
    }
};
