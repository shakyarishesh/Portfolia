<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Multi-portfolio: every content row belongs to a portfolio. Existing content moves to one portfolio, nothing is dropped.
return new class extends Migration
{
    /** Tables whose rows belong to exactly one portfolio. */
    private const SCOPED = [
        'profiles', 'sections', 'skill_categories', 'skills', 'experiences', 'projects', 'education', 'certifications',
        'achievements', 'expertise_areas', 'social_links', 'posts', 'testimonials', 'media', 'contact_messages',
        'page_views', 'conversation_imports', 'imported_conversations', 'proposed_changes', 'audit_logs',
    ];

    /** Unique per portfolio instead of globally: table => [column, old index]. */
    private const PER_PORTFOLIO_UNIQUE = [
        'sections' => ['key', 'sections_key_unique'],
        'skill_categories' => ['name', 'skill_categories_name_unique'],
        'projects' => ['slug', 'projects_slug_unique'],
        'posts' => ['slug', 'posts_slug_unique'],
    ];

    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // owner / contact person
            $table->string('name');
            $table->string('username', 40)->unique(); // always stored lower-case
            $table->string('template', 40)->default('classic'); // live template
            $table->string('draft_template', 40)->nullable(); // chosen but not yet published
            $table->json('template_settings')->nullable(); // {template_key: {setting: value}}
            $table->string('status', 20)->default('draft')->index(); // draft | published | disabled | archived
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->string('og_image_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        foreach (self::SCOPED as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('portfolio_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->string('kind', 20)->default('article')->index()->after('slug'); // article (dated) | note (evergreen)
        });

        $this->backfill();

        foreach (self::PER_PORTFOLIO_UNIQUE as $name => [$column, $index]) {
            Schema::table($name, function (Blueprint $table) use ($column, $index) {
                $table->dropUnique($index);
                $table->unique(['portfolio_id', $column]);
            });
        }
    }

    private function backfill(): void
    {
        $profile = DB::table('profiles')->orderBy('id')->first();
        if (! $profile) {
            return;
        }

        $username = Str::slug(env('PORTFOLIO_DEFAULT_USERNAME') ?: Str::before(trim($profile->full_name), ' ')) ?: 'portfolio';
        $id = DB::table('portfolios')->insertGetId([
            'name' => $profile->full_name,
            'username' => mb_strtolower($username),
            'template' => 'classic',
            'status' => 'published',
            'seo_description' => $profile->meta_description,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::SCOPED as $name) {
            DB::table($name)->whereNull('portfolio_id')->update(['portfolio_id' => $id]);
        }

        // Blog and notes become sections so each portfolio can switch them on or off.
        $max = (int) DB::table('sections')->max('sort_order');
        foreach ([['blog', 'Blog', 'Writing'], ['notes', 'Notes', 'Notes']] as $i => [$key, $label, $title]) {
            if (! DB::table('sections')->where('key', $key)->exists()) {
                DB::table('sections')->insert(['portfolio_id' => $id, 'key' => $key, 'nav_label' => $label, 'title' => $title,
                    'sort_order' => $max + $i + 1, 'is_visible' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('sections')->whereIn('key', ['blog', 'notes'])->delete();

        foreach (self::PER_PORTFOLIO_UNIQUE as $name => [$column, $index]) {
            Schema::table($name, function (Blueprint $table) use ($column) {
                $table->dropForeign(['portfolio_id']);
                $table->dropUnique(['portfolio_id', $column]);
                $table->unique($column);
            });
        }

        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn('kind'));

        foreach (self::SCOPED as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! array_key_exists($name, self::PER_PORTFOLIO_UNIQUE)) {
                    $table->dropForeign(['portfolio_id']);
                }
                $table->dropColumn('portfolio_id');
            });
        }

        Schema::dropIfExists('portfolios');
    }
};
