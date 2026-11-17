<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Additive only: no existing column is changed or dropped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->index()->after('tagline');
            $table->text('problem')->nullable()->after('description');
            $table->text('challenges')->nullable()->after('architecture');
            $table->text('diagram')->nullable()->after('challenges'); // Mermaid source
            $table->string('meta_description', 300)->nullable()->after('repo_url');
        });

        Schema::table('skills', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
        });

        // CV versions: every uploaded resume is kept; exactly one is active.
        Schema::table('media', function (Blueprint $table) {
            $table->boolean('is_active')->default(false);
        });
        DB::table('media')->where('collection', 'resume')->update(['is_active' => true]);

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body')->nullable(); // Markdown
            $table->string('category', 60)->nullable()->index();
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable()->index(); // future date = scheduled
            $table->string('meta_description', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable();
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('company')->nullable();
            $table->text('quote');
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('source_note')->nullable(); // where permission to publish came from
            $table->timestamps();
        });

        // Privacy: no IPs or user agents are stored, only a salted hash that changes daily.
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // page | project | post | resume | contact
            $table->string('subject', 160)->nullable(); // slug or path
            $table->char('visitor', 16);
            $table->string('referrer_host', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['created_at', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('posts');
        Schema::table('media', fn (Blueprint $t) => $t->dropColumn('is_active'));
        Schema::table('contact_messages', fn (Blueprint $t) => $t->dropColumn(['replied_at', 'archived_at']));
        Schema::table('skills', fn (Blueprint $t) => $t->dropColumn('description'));
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn(['category', 'problem', 'challenges', 'diagram', 'meta_description']));
    }
};
