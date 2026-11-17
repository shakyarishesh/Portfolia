<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table: the person the portfolio is about.
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('headline');
            $table->json('typed_phrases')->nullable();
            $table->text('intro')->nullable();
            $table->text('about')->nullable();
            $table->string('location')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('show_phone')->default(false);
            $table->string('avatar_url')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->boolean('open_to_work')->default(true);
            $table->boolean('contact_form_enabled')->default(true);
            $table->text('source_note')->nullable();
            $table->timestamps();
        });

        // Public tabs: visibility and order are editable from the admin panel.
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('nav_label');
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('skill_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('level')->nullable(); // 1 familiar … 4 expert; null = not rated
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
            $table->unique(['skill_category_id', 'name']);
        });

        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('company_url')->nullable();
            $table->string('role');
            $table->string('employment_type', 30)->default('full_time');
            $table->string('location')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('summary')->nullable();
            $table->json('highlights')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->text('architecture')->nullable();
            $table->string('role')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('live_url')->nullable();
            $table->string('repo_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
        });

        Schema::create('technologies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('technologables', function (Blueprint $table) {
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->morphs('technologable');
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['technology_id', 'technologable_id', 'technologable_type'], 'technologables_primary');
        });

        Schema::create('education', function (Blueprint $table) {
            $table->id();
            $table->string('institution');
            $table->string('degree');
            $table->string('field')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('issuer');
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('credential_id')->nullable();
            $table->string('credential_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
        });

        // Engineering highlights (cards) and career milestones (timeline).
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('highlight')->index();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('details')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('label')->nullable();
            $table->string('date_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
        });

        Schema::create('expertise_areas', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
        });

        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 40);
            $table->string('label');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['social_links', 'expertise_areas', 'achievements', 'certifications', 'education', 'technologables',
            'technologies', 'projects', 'experiences', 'skills', 'skill_categories', 'sections', 'profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
