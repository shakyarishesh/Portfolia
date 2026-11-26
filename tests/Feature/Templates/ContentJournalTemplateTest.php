<?php

namespace Tests\Feature\Templates;

use App\Models\Portfolio;
use App\Models\Post;
use App\Models\Section;
use App\Support\CurrentPortfolio;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentJournalTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(PortfolioSeeder::class);
        $this->useTemplate();
    }

    private function useTemplate(array $settings = []): void
    {
        Portfolio::where('username', 'rishesh')->update([
            'template' => 'content_journal',
            'template_settings' => json_encode(['content_journal' => $settings]),
        ]);
    }

    private function entry(array $attributes): Post
    {
        return Post::create($attributes + [
            'kind' => 'article', 'status' => 'published', 'published_at' => now()->subDay(),
            'body' => "## First part\n\nSome words.\n\n## Second part\n\nMore words.",
        ]);
    }

    private function notesAndArticles(): void
    {
        $this->entry(['title' => 'Zettel on queues', 'slug' => 'queues', 'kind' => 'note', 'category' => 'Backend', 'excerpt' => 'Evergreen queue notes.']);
        $this->entry(['title' => 'Draft note', 'slug' => 'draft-note', 'kind' => 'note', 'status' => 'draft']);
        foreach (range(1, 6) as $i) {
            $this->entry(['title' => "Article number {$i}", 'slug' => "article-{$i}", 'published_at' => now()->subDays($i), 'category' => 'Engineering', 'excerpt' => "Excerpt {$i}"]);
        }
        $this->entry(['title' => 'Scheduled article', 'slug' => 'later', 'published_at' => now()->addDay()]);
    }

    public function test_home_is_a_content_hub_with_separate_notes_and_articles(): void
    {
        $this->notesAndArticles();

        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('data-template="content_journal"', false)
            ->assertSee('Rishesh')
            ->assertSee('id="writing"', false)->assertSee('id="notes"', false)->assertSee('id="contact"', false)
            ->assertSee('Zettel on queues')->assertSee('Article number 1')->assertSee('min read')
            ->assertDontSee('Draft note')->assertDontSee('Scheduled article')
            ->assertDontSee('id="now"', false)->assertDontSee('id="uses"', false)
            ->assertSee('"@type":"Person"', false)
            ->getContent();

        // Default home_posts = 5; classic markers absent.
        $this->assertSame(5, substr_count($html, 'class="cj-entry"'));
        $this->assertSame(1, substr_count($html, '<h1'));
        foreach (['cursor-glow', 'intro-screen', 'class="bg"'] as $classic) {
            $this->assertStringNotContainsString($classic, $html);
        }
    }

    public function test_now_uses_and_home_posts_settings(): void
    {
        $this->notesAndArticles();
        $this->useTemplate(['now' => "Learning **Rust**.\n\n<script>alert(1)</script>", 'uses' => '[x](javascript:alert(1)) Neovim', 'home_posts' => '3', 'theme' => 'dark']);

        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('id="now"', false)->assertSee('<strong>Rust</strong>', false)
            ->assertSee('id="uses"', false)->assertSee('Neovim')
            ->assertSee('data-default="dark"', false)
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertSame(3, substr_count($html, 'class="cj-entry"'));
    }

    public function test_list_pages_show_the_right_kind_and_filter(): void
    {
        $this->notesAndArticles();
        $this->entry(['title' => 'Websocket deep dive', 'slug' => 'ws', 'category' => 'Realtime', 'tags' => ['realtime'], 'body' => 'Channels and presence.']);

        $this->get('/rishesh/notes')->assertOk()->assertSee('Zettel on queues')->assertDontSee('Article number 1')
            ->assertDontSee('Draft note')->assertSee('Search notes');
        $this->get('/rishesh/blog')->assertOk()->assertSee('Article number 1')->assertDontSee('Zettel on queues')
            ->assertDontSee('Scheduled article')->assertSee('Search articles');

        $this->get('/rishesh/blog?q=presence')->assertOk()->assertSee('Websocket deep dive')->assertDontSee('Article number 1')->assertSee('Clear filters');
        $this->get('/rishesh/blog?tag=realtime')->assertSee('Websocket deep dive')->assertDontSee('Article number 2');
        $this->get('/rishesh/blog?category=Engineering')->assertSee('Article number 2')->assertDontSee('Websocket deep dive');
        $this->get('/rishesh/blog?q=nothing-matches-this')->assertOk()->assertSee('No articles match those filters.');
    }

    public function test_post_pages_render_toc_reading_time_and_dates(): void
    {
        $this->notesAndArticles();

        $this->get('/rishesh/blog/article-1')->assertOk()
            ->assertSee('Contents')->assertSee('href="#first-part"', false)->assertSee('<h2 id="second-part">', false)
            ->assertSee('1 min read')->assertSee('Published')
            ->assertSee('BlogPosting', false)->assertSee('og:type" content="article"', false);

        $this->get('/rishesh/notes/queues')->assertOk()->assertSee('Last updated')->assertDontSee('>Published', false);
        $this->get('/rishesh/blog/later')->assertNotFound();
        $this->get('/rishesh/notes/draft-note')->assertNotFound();
    }

    public function test_hidden_notes_section_removes_notes(): void
    {
        $this->notesAndArticles();
        Section::where('key', 'notes')->update(['is_visible' => false]);

        $this->get('/rishesh')->assertOk()->assertDontSee('Zettel on queues')->assertDontSee('id="notes"', false);
        $this->get('/rishesh/notes')->assertNotFound();
    }

    public function test_project_page_and_contact_form(): void
    {
        $project = \App\Models\Project::published()->firstOrFail();
        $this->get("/rishesh/projects/{$project->slug}")->assertOk()->assertSee($project->name)->assertSee('data-template="content_journal"', false);

        \App\Models\Profile::query()->update(['contact_form_enabled' => true]);
        $this->get('/rishesh')->assertSee('name="website"', false)->assertSee('action="'.route('contact.store').'"', false);
    }

    public function test_empty_portfolio_renders(): void
    {
        $p = $this->portfolio(['name' => 'Empty', 'username' => 'empty-one'], ['headline' => '', 'intro' => null, 'email' => null]);
        $p->update(['template' => 'content_journal']);
        CurrentPortfolio::set(null);

        $this->get('/empty-one')->assertOk()->assertSee('data-template="content_journal"', false);
        $this->get('/empty-one/blog')->assertOk()->assertSee('No articles published yet.');
        $this->get('/empty-one/notes')->assertOk()->assertSee('No notes published yet.');
    }
}
