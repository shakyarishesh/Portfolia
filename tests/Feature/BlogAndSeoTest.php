<?php

namespace Tests\Feature;

use App\Admin\Resources;
use App\Models\Post;
use App\Models\Project;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogAndSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(PortfolioSeeder::class);
    }

    private function article(array $attributes = []): Post
    {
        return Post::create($attributes + [
            'title' => 'Designing payroll engines', 'slug' => 'payroll-engines', 'status' => 'published', 'published_at' => now()->subDay(),
            'excerpt' => 'How I structure salary calculations.', 'tags' => ['laravel', 'payroll'], 'category' => 'Backend',
            'body' => "## Why\n\nBecause.\n\n## How\n\n```php\necho 1;\n```\n\n<script>alert(1)</script> [bad](javascript:alert(1))",
        ]);
    }

    public function test_published_article_renders_with_toc_and_escaped_html(): void
    {
        $this->article();

        $html = $this->get('/rishesh/blog/payroll-engines')->assertOk()
            ->assertSee('<h2 id="why">Why</h2>', false)
            ->assertSee('href="#how"', false)
            ->assertSee('language-php', false)
            ->assertSee('BlogPosting', false)
            ->assertSee('1 min read')
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_drafts_and_scheduled_articles_are_hidden(): void
    {
        $this->article(['slug' => 'draft', 'title' => 'Draft one', 'status' => 'draft']);
        $this->article(['slug' => 'later', 'title' => 'Scheduled one', 'published_at' => now()->addDay()]);

        $this->get('/rishesh/blog/draft')->assertNotFound();
        $this->get('/rishesh/blog/later')->assertNotFound();
        $this->get('/rishesh/blog')->assertOk()->assertDontSee('Draft one')->assertDontSee('Scheduled one')->assertSee('No articles published yet');
        // no live posts → no Blog link on the home page
        $this->get('/rishesh')->assertDontSee('>Blog</a>', false);
    }

    public function test_blog_search_tag_filter_and_related(): void
    {
        $this->article();
        $this->article(['slug' => 'apis', 'title' => 'Building REST APIs', 'tags' => ['laravel', 'api'], 'body' => 'Resources and versioning.']);
        $this->article(['slug' => 'ws', 'title' => 'WebSockets 101', 'tags' => ['realtime'], 'category' => 'Realtime', 'body' => 'Channels.']);

        $this->get('/rishesh/blog?tag=api')->assertSee('Building REST APIs')->assertDontSee('WebSockets 101');
        $this->get('/rishesh/blog?q=versioning')->assertSee('Building REST APIs')->assertDontSee('Designing payroll engines');
        $this->get('/rishesh/blog/payroll-engines')->assertSee('Related articles')->assertSee('Building REST APIs')->assertDontSee('WebSockets 101');
        $this->get('/rishesh')->assertSee('>Blog</a>', false);
    }

    public function test_admin_creates_article_and_previews_it(): void
    {
        $this->admin();
        $this->post('/admin/posts', ['title' => 'Query optimisation notes', 'body' => '## Indexes', 'tags' => "mysql\nperformance", 'status' => 'published'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $post = Post::where('title', 'Query optimisation notes')->firstOrFail();
        $this->assertSame('query-optimisation-notes', $post->slug);
        $this->assertSame(['mysql', 'performance'], $post->tags);
        $this->assertNotNull($post->published_at, 'publishing without a date publishes now');
        $this->get("/rishesh/blog/{$post->slug}")->assertOk();

        $post->update(['status' => 'draft']);
        $this->get("/admin/posts/{$post->id}/preview")->assertOk()->assertSee('Preview')->assertSee('noindex', false);
        $this->get("/admin/posts/{$post->id}/edit")->assertOk()->assertSee('Cover image');
    }

    public function test_preview_requires_admin(): void
    {
        $post = $this->article(['status' => 'draft']);
        $this->get("/admin/posts/{$post->id}/preview")->assertRedirect('/admin/login');
    }

    public function test_sitemap_and_robots(): void
    {
        $this->article();
        Project::where('slug', 'dialinger')->update(['status' => 'draft']);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();
        $this->assertStringContainsString(route('blog.show', 'payroll-engines'), $xml);
        $this->assertStringContainsString(route('projects.show', 'hajir'), $xml);
        $this->assertStringNotContainsString('/projects/dialinger', $xml);

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_posts_and_testimonials_are_not_offered_to_the_importer(): void
    {
        $keys = array_map(fn ($r) => $r::KEY, Resources::importable());
        $this->assertNotContains('post', $keys);
        $this->assertNotContains('testimonial', $keys);
        $this->assertContains('project', $keys);
    }
}
