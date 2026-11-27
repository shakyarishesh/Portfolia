<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/** /create "Fill in from my CV": Gemini fills the details template from the PDF, the existing parser fills the form. */
class ApplicationCvImportTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI = 'generativelanguage.googleapis.com/*';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.key' => 'test-key', 'services.gemini.daily_limit' => 100,
            'services.gemini.model' => 'main-model', 'services.gemini.fallback_model' => 'lite-model']);
        Sleep::fake();
    }

    private function upload(string $name = 'cv.pdf', string $mime = 'application/pdf')
    {
        return $this->post('/create/cv', ['type' => 'technical', 'cv' => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nfake cv")->mimeType($mime)]);
    }

    /** What the model returns: the real template, answered, wrapped in a code fence it was told not to use. */
    private function fakeGemini(): void
    {
        $text = $this->get('/create/template?type=technical')->getContent();
        $text = str_replace("\nFull name: \n", "\nFull name: Jane Doe\n", $text);
        $text = preg_replace('/(== Experience ==.*?\nCompany: )/s', '${1}Acme Ltd', $text);
        Http::fake([self::GEMINI => $this->answer($text)]);
    }

    private function answer(string $text)
    {
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => "```text\n{$text}\n```"]]]]]]);
    }

    public function test_cv_upload_is_offered_only_when_a_key_is_set(): void
    {
        $this->get('/create?type=technical')->assertSee('Fill in from my CV')->assertSee('Download the details template');
        config(['services.gemini.key' => null]);
        $this->get('/create?type=technical')->assertDontSee('Fill in from my CV')->assertSee('Download the details template');
        $this->upload()->assertNotFound();
    }

    public function test_cv_fills_the_form_for_review(): void
    {
        $this->fakeGemini();
        $this->upload()->assertRedirect(route('apply.create', ['type' => 'technical']))
            ->assertSessionHasNoErrors()
            ->assertSessionHasInput('full_name', 'Jane Doe')
            ->assertSessionHasInput('experience.0.company', 'Acme Ltd')
            ->assertSessionHas('import_warnings', []);

        Http::assertSent(fn (Request $r) => $r->hasHeader('x-goog-api-key', 'test-key')
            && $r['contents'][0]['parts'][0]['inline_data']['mime_type'] === 'application/pdf'
            && str_contains($r['contents'][0]['parts'][1]['text'], '== Experience =='));
    }

    public function test_two_cvs_per_ip_per_day_and_invalid_files_do_not_count(): void
    {
        $this->fakeGemini();
        $this->upload('cv.txt', 'text/plain')->assertSessionHasErrors('cv');
        $this->upload()->assertSessionHasNoErrors();
        $this->upload()->assertSessionHasNoErrors();
        $this->upload()->assertSessionHasErrors(['cv' => 'You can read in up to 2 CVs a day. Please use the template below instead, or fill in the form.']);
        Http::assertSentCount(2);
    }

    public function test_site_wide_daily_cap(): void
    {
        config(['services.gemini.daily_limit' => 1]);
        $this->fakeGemini();
        $this->upload()->assertSessionHasNoErrors();
        $this->upload()->assertSessionHasErrors('cv');
        Http::assertSentCount(1);
    }

    public function test_busy_model_is_retried_then_the_fallback_model_is_used(): void
    {
        $busy = Http::response(['error' => ['message' => 'high demand']], 503);
        Http::fake([
            '*/models/main-model:*' => $busy,
            '*/models/lite-model:*' => Http::sequence()->pushResponse($busy)->pushResponse($this->answer("== Profile ==\nFull name: Jane Doe")),
        ]);
        $this->upload()->assertSessionHasNoErrors()->assertSessionHasInput('full_name', 'Jane Doe');
        Http::assertSentCount(5); // 3 tries of the main model, 2 of the fallback
    }

    public function test_api_failure_falls_back_to_the_template_and_does_not_use_an_attempt(): void
    {
        $quota = Http::response(['error' => ['message' => 'quota']], 429);
        $ok = $this->answer("== Profile ==\nFull name: Jane Doe");
        Http::fake([self::GEMINI => Http::sequence()->pushResponse($quota)->pushResponse($quota)->pushResponse($quota)
            ->pushResponse($quota)->pushResponse($quota)->pushResponse($quota)->pushResponse($ok)->pushResponse($ok)]);
        $this->upload()->assertSessionHasErrors('cv')->assertSessionMissing('_old_input.full_name');
        Http::assertSentCount(6);

        $this->upload()->assertSessionHasNoErrors();
        $this->upload()->assertSessionHasNoErrors();
    }
}
