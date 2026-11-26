<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Portfolio types (technical, creative, business …). Additive only: existing portfolios become "technical", nothing is dropped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->string('icon', 16)->nullable(); // an emoji
            $table->string('description', 300)->nullable();
            $table->string('example', 300)->nullable(); // "what you can showcase"
            $table->json('categories')->nullable(); // professional categories, e.g. "Photographers"
            $table->json('modules')->nullable(); // keys of config portfolio.modules
            $table->json('sections')->nullable(); // section keys a new portfolio of this type starts with, in order
            $table->json('templates')->nullable(); // compatible template keys
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $order = 0;
        foreach (config('portfolio.types', []) as $slug => $t) {
            DB::table('portfolio_types')->insert([
                'slug' => $slug, 'name' => $t['name'], 'icon' => $t['icon'] ?? null, 'description' => $t['description'] ?? null,
                'example' => $t['example'] ?? null, 'categories' => json_encode($t['categories'] ?? []), 'modules' => json_encode($t['modules'] ?? []),
                'sections' => json_encode($t['sections'] ?? []), 'templates' => json_encode($t['templates'] ?? []),
                'is_active' => true, 'sort_order' => ++$order, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('type', 40)->default('technical')->index()->after('username'); // existing portfolios: technical
            $table->json('disciplines')->nullable()->after('type'); // Hybrid: the combined type slugs
        });

        // Profession-specific project fields (config portfolio.project_details), validated per module.
        Schema::table('projects', function (Blueprint $table) {
            $table->json('details')->nullable()->after('architecture');
        });

        // Free-text sections (Teaching philosophy, Publications, custom ones …): Markdown.
        Schema::table('sections', function (Blueprint $table) {
            $table->text('body')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('sections', fn (Blueprint $t) => $t->dropColumn('body'));
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('details'));
        Schema::table('portfolios', function (Blueprint $t) {
            $t->dropIndex(['type']);
            $t->dropColumn(['type', 'disciplines']);
        });
        Schema::dropIfExists('portfolio_types');
    }
};
