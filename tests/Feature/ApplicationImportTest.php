<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Skill;
use App\Support\CurrentPortfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Offline route of /create: download the details template, fill it in, upload it to pre-fill the form. */
class ApplicationImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(string $text)
    {
        return $this->post('/create/import', ['details' => UploadedFile::fake()->createWithContent('details.txt', $text)]);
    }

    /** The downloaded template with answers written after the given labels (first match after the section heading). */
    private function filled(array $answers): string
    {
        $text = $this->get('/create/template')->getContent();
        foreach ($answers as [$section, $label, $value]) {
            $at = strpos($text, "== {$section} ==");
            $pos = strpos($text, "\n{$label}:", $at);
            $end = strpos($text, "\n", $pos + 1);
            $text = substr($text, 0, $pos)."\n{$label}: {$value}".substr($text, $end);
        }

        return $text;
    }

    public function test_template_download_lists_every_section_and_field_with_hints(): void
    {
        $res = $this->get('/create/template')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="portfolio-details.txt"');
        $text = $res->getContent();
        foreach (['Your portfolio', 'Profile', 'Experience', 'Projects', 'Skills', 'Education', 'Certifications', 'Professional links', 'Areas of expertise', 'Testimonials'] as $s) {
            $this->assertStringContainsString("== {$s} ==", $text);
        }
        $this->assertStringContainsString("\nFull name: \n", $text);
        $this->assertStringContainsString("\nTemplate: Classic", $text);
        $this->assertStringContainsString('One of: Full-time, Part-time', $text);
        $this->get('/create')->assertSee(route('apply.template'))->assertSee('Fill in the form');
    }

    public function test_round_trip_fills_the_form_then_submits(): void
    {
        $text = $this->filled([
            ['Your portfolio', 'Username', 'jane-doe'],
            ['Your portfolio', 'Template', 'gradient'],
            ['Profile', 'Full name', 'Jane Doe'],
            ['Profile', 'Email', 'jane@example.com'],
            ['Profile', 'About me', "First paragraph.\nStill first: with a colon.\n\nSecond paragraph."],
            ['Profile', 'Open to new opportunities', 'Yes'],
            ['Experience', 'Company', 'Acme'],
            ['Experience', 'Role / title', 'Engineer'],
            ['Experience', 'Type', 'contract'],
            ['Experience', 'Start date', 'March 2021'],
            ['Experience', 'End date', 'present'],
            ['Experience', 'Responsibilities & achievements', "Shipped X\nLed Y"],
            ['Skills', 'Category', 'Backend'],
            ['Skills', 'Skills', 'PHP, Laravel'],
        ]);
        // A second job: copy of the block after ---.
        $text = str_replace("== Projects ==", "---\nCompany: Beta\nRole / title: Intern\nType: Internship\nStart date: 2019-06\nEnd date: Aug 2019\n\n== Projects ==", $text);

        $this->upload($text)->assertRedirect('/create')->assertSessionHas('import_warnings', []);
        $old = session()->getOldInput();
        $this->assertSame('jane-doe', $old['username']);
        $this->assertSame('gradient', $old['template']);
        $this->assertSame("First paragraph.\nStill first: with a colon.\n\nSecond paragraph.", $old['about']);
        $this->assertSame('1', $old['open_to_work']);
        $this->assertSame(['company' => 'Acme', 'role' => 'Engineer', 'employment_type' => 'contract', 'start_date' => '2021-03-01', 'is_current' => '1', 'highlights' => "Shipped X\nLed Y"], $old['experience'][0]);
        $this->assertSame(['company' => 'Beta', 'role' => 'Intern', 'employment_type' => 'internship', 'start_date' => '2019-06-01', 'end_date' => '2019-08-01'], $old['experience'][1]);
        $this->assertArrayNotHasKey('projects', $old); // blank sections are left out

        // The form shows it for review…
        $this->get('/create')->assertOk()->assertSee('value="jane-doe"', false)->assertSee('Acme')->assertSee('Beta')->assertSee('filled in from your file');

        // …and submitting it as-is creates the pending portfolio.
        $this->post('/create', $old)->assertRedirect(route('apply.submitted'));
        $p = Portfolio::where('username', 'jane-doe')->firstOrFail();
        $this->assertSame(['pending', 'gradient'], [$p->status, $p->template]);
        CurrentPortfolio::using($p, function () {
            $this->assertSame(['Acme', 'Beta'], Experience::orderBy('sort_order')->pluck('company')->all());
            $this->assertSame(['PHP', 'Laravel'], Skill::orderBy('sort_order')->pluck('name')->all());
            $this->assertSame(0, Project::count());
        });
    }

    public function test_unusable_lines_are_reported_not_guessed(): void
    {
        $text = $this->filled([
            ['Profile', 'Full name', 'Jane'],
            ['Experience', 'Company', 'Acme'],
            ['Experience', 'Type', 'Sometimes'],
            ['Experience', 'Start date', 'whenever'],
        ]);
        $text = str_replace("== Profile ==", "== Profile ==\nFavourite colour: blue", $text)."\n== Hobbies ==\nChess: yes\n";

        $this->upload($text)->assertRedirect('/create');
        $warnings = implode("\n", session('import_warnings'));
        $this->assertStringContainsString('"Favourite colour: blue") is not a field', $warnings);
        $this->assertStringContainsString('"Sometimes" is not an option for Type (experience)', $warnings);
        $this->assertStringContainsString('"whenever" is not a date', $warnings);
        $this->assertStringContainsString('Unknown section "Hobbies"', $warnings);
        $this->assertSame(['company' => 'Acme'], session()->getOldInput('experience.0'));
        $this->get('/create')->assertSee('Some parts of your file')->assertSee('Unknown section');
    }

    public function test_only_plain_text_files_are_accepted_and_nothing_is_stored(): void
    {
        $this->post('/create/import', ['details' => UploadedFile::fake()->image('me.png')])->assertSessionHasErrors('details');
        $this->post('/create/import', [])->assertSessionHasErrors('details');
        $this->upload("\xEF\xBB\xBF== Profile ==\r\nFull name: Zoë\r\n")->assertSessionHasNoErrors();
        $this->assertSame('Zoë', session()->getOldInput('full_name'));
        $this->assertSame(0, Portfolio::count());
    }
}
