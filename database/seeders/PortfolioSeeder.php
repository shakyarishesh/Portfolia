<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Education;
use App\Models\Experience;
use App\Models\ExpertiseArea;
use App\Models\Portfolio;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Section;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialLink;
use App\Support\AuditContext;
use App\Support\CurrentPortfolio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Initial content, limited to facts with evidence:
 *  CV  = rishesh_shakya.pdf (the owner's own CV)
 *  GIT = commit history of hajir-v2 / hajir_dashboard
 *  MEM = Claude Code memory notes and transcripts from the veloxlabs projects
 * Anything uncertain is seeded as "needs_review" so it stays off the public site until checked.
 */
class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        if (Portfolio::query()->exists()) {
            $this->command?->warn('Portfolio content already exists — skipping. Use `php artisan migrate:fresh --seed` to start over.');

            return;
        }

        $portfolio = Portfolio::create(['name' => 'Rishesh Shakya', 'username' => 'rishesh', 'template' => 'classic', 'status' => 'published', 'published_at' => now()]);

        // Left current afterwards so code that runs after seeding (tests, tinker) works inside this portfolio.
        CurrentPortfolio::set($portfolio);
        AuditContext::run('seed', function () {
            $this->profile();
            $this->sections();
            [$velox, $times] = $this->experience();
            $projects = $this->projects($velox);
            $this->achievements($projects['hajir']);
            $this->skills();
            $this->expertise();
            $this->education();
            $this->links();
        });
    }

    private function profile(): void
    {
        $profile = Profile::create([
            'full_name' => 'Rishesh Shakya',
            'headline' => 'Backend Developer',
            'typed_phrases' => ['payroll engines.', 'tax calculators that pass audits.', 'RESTful Laravel APIs.', 'cloud telephony with Twilio.', 'Stripe-powered billing.', 'systems people get paid by.'],
            'intro' => "Backend developer at [Velox Labs](https://velox.com.np/) who turns complex business rules into fast, reliable Laravel systems. Since 2024 I've been building the payroll and tax engine behind **Hajir** — salaries, overtime, fiscal-year splits and tax that companies run every month — and shipped **Dialinger**, a Twilio- and Stripe-powered cloud telephony platform. I care about clean REST APIs, well-designed relational schemas and queries that stay fast as the data grows.",
            'about' => "I'm a backend developer based in Lalitpur, Nepal, with a Bachelor's in Information Technology from Patan Multiple Campus. Most of my days are spent in **Laravel** — designing REST APIs, modelling money, time and rules that don't fit neatly into a schema, and making slow queries fast.\n\nI like problems where being \"almost right\" is a bug: Nepal's income-tax law, Bikram Sambat vs. Gregorian calendars, mid-year salary revisions, people who join on the 17th and leave on the 3rd. I write the scenario down first and let it drive the code and the tests.\n\nWhen something is off by a few rupees in production, I'm usually the one tracing it to the root — and fixing it once, where every caller goes through.",
            'location' => 'Lalitpur, Nepal',
            'email' => 'shakyarises1211@gmail.com',
            'phone' => '+977-9841701082',
            'show_phone' => true,
            'avatar_url' => 'https://github.com/shakyarishesh.png?size=640',
            'meta_description' => 'Rishesh Shakya — backend developer in Lalitpur, Nepal, building payroll, tax and telephony systems with Laravel, REST APIs and MySQL.',
            'open_to_work' => true,
            'contact_form_enabled' => true,
            'source_note' => 'CV (contact, location, summary). A GitHub README in the transcripts uses shakyarishesh@gmail.com — confirm which email to show.',
        ]);

        // Attach the CV as the downloadable resume when the file is available.
        $cv = env('SEED_RESUME_PATH', base_path('../rishesh_shakya.pdf'));
        if (is_file($cv)) {
            $path = 'resume/'.basename($cv);
            Storage::disk('local')->put($path, file_get_contents($cv));
            $profile->media()->create([
                'collection' => 'resume', 'disk' => 'local', 'path' => $path,
                'original_name' => basename($cv), 'mime_type' => 'application/pdf', 'size' => filesize($cv), 'is_active' => true,
            ]);
        }
    }

    private function sections(): void
    {
        foreach ([
            ['home', 'Home', "Hello, I'm", 'About me'],
            ['experience', 'Experience', 'Experience', 'Where I have worked'],
            ['projects', 'Projects', 'Selected work', "Products & systems I've built"],
            ['skills', 'Skills', 'Toolbox', 'What I work with'],
            ['education', 'Education', 'Education', 'Where I studied'],
            ['contact', 'Contact', 'Contact', "Let's build something that adds up."],
            ['blog', 'Blog', null, 'Writing'],
            ['notes', 'Notes', null, 'Notes'],
        ] as $i => [$key, $label, $eyebrow, $title]) {
            Section::create(['key' => $key, 'nav_label' => $label, 'eyebrow' => $eyebrow, 'title' => $title, 'sort_order' => $i + 1]);
        }
    }

    private function experience(): array
    {
        $velox = Experience::create([
            'company' => 'Velox Labs', 'company_url' => 'https://velox.com.np/', 'role' => 'Software Developer',
            'employment_type' => 'full_time', 'location' => 'Kathmandu, Nepal', 'start_date' => '2024-10-01', 'is_current' => true,
            'summary' => 'Backend developer on Hajir (HR, attendance and payroll SaaS) and Dialinger (cloud telephony). I take features end to end — Laravel backend, APIs for the web and mobile apps, and the matching Next.js screens.',
            'highlights' => [
                'Designed and implemented RESTful APIs in Laravel for efficient backend communication.',
                'Reduced query execution times and optimized database schemas for better performance.',
                'Integrated third-party services such as Stripe and Twilio for payments and communication.',
                'Built most of the payroll and tax engine for Hajir — 5,600+ backend commits and 500+ frontend commits.',
            ],
            'sort_order' => 1, 'status' => 'published',
            'source_note' => 'CV (title, dates, bullets; "strip" typo corrected to Stripe). GIT: 5,675 commits in hajir-v2 and 550 in hajir_dashboard under the owner\'s emails; first hajir-v2 commit 2024-12-12 (CV says Oct 2024 — earlier work may predate the repo). An older CV draft titled the role "Backend Developer".',
        ]);
        $velox->syncTechnologies(['PHP', 'Laravel', 'MySQL', 'REST APIs', 'Next.js', 'React', 'Stripe', 'Twilio', 'Docker', 'PHPUnit']);

        $times = Experience::create([
            'company' => 'Times Global', 'company_url' => 'https://www.timesglobal.com.np/', 'role' => 'Backend Developer Intern',
            'employment_type' => 'internship', 'location' => 'Kathmandu, Nepal', 'start_date' => '2024-06-01', 'end_date' => '2024-10-31',
            'highlights' => [
                'Built and optimized Laravel Blade templates for data tables, views and administrative workflows.',
                'Assisted in backend feature development and bug fixing in production environments.',
            ],
            'sort_order' => 2, 'status' => 'published',
            'source_note' => 'CV only.',
        ]);
        $times->syncTechnologies(['PHP', 'Laravel', 'Blade', 'MySQL']);

        return [$velox, $times];
    }

    private function projects(Experience $velox): array
    {
        $hajir = Project::create([
            'experience_id' => $velox->id, 'name' => 'Hajir', 'slug' => 'hajir',
            'tagline' => 'HR, attendance and payroll SaaS tailored for companies in Nepal.',
            'description' => "Hajir handles attendance, shifts, leave and payroll for companies across Nepal. I develop and maintain core backend modules, and own large parts of the payroll and tax engine — the code that decides what employees are paid, taxed and deposited each month.",
            'features' => [
                'Daily salary calculation including overtime, holiday salary and paid leave.',
                'Advance salary and payback management with accurate remaining-balance calculation based on payment status.',
                'Dynamic payroll driven by rule sets configured by the super admin, with backend-generated columns and complex tax logic.',
                'Migration from the old version to the new system, transforming data for compatibility.',
                'Fiscal-year split payroll for accurate salary, tax, benefits and deductions across fiscal-year boundaries.',
                'Payroll tax engine supporting multiple payroll frequencies (monthly and semi-monthly) and branch/department-wise tax.',
                'Salary matrix (designation / level / grade) with cumulative grade progression and automatic increments.',
                'Offboarding settlement, gratuity payout on the final payroll run, and employee self-service SSF/tax contribution reporting.',
            ],
            'architecture' => "Laravel 11 backend (modular, using nwidart/laravel-modules) with a MySQL database, queued and scheduled jobs for nightly calculations, Sanctum/Passport authentication, Excel and PDF exports, and PHPUnit feature tests. The web dashboard is Next.js with MUI and Redux Toolkit; a mobile app consumes the same APIs.",
            'role' => 'Backend developer (payroll & tax), also frontend for the features I ship',
            'start_date' => '2024-10-01', 'is_current' => true, 'live_url' => 'https://web.hajirhr.com/',
            'is_featured' => true, 'sort_order' => 1, 'status' => 'published',
            'source_note' => 'CV (features, dates, URL). MEM/GIT for architecture and stack. Shift management was originally built by another developer and extended by the owner — wording kept as "maintain".',
        ]);
        $hajir->syncTechnologies(['Laravel', 'PHP', 'MySQL', 'Queues', 'PHPUnit', 'Next.js', 'MUI', 'Redux Toolkit']);

        $dialinger = Project::create([
            'experience_id' => $velox->id, 'name' => 'Dialinger', 'slug' => 'dialinger',
            'tagline' => 'Cloud-based telephony: calls, SMS, MMS, logs and analytics.',
            'description' => 'A cloud telephony application for making and receiving calls and messages from the browser, with usage analytics and subscription billing.',
            'features' => [
                'Calls, SMS, MMS, call logs and analytics.',
                'Twilio SDK integration, Stripe subscriptions and KYC workflows.',
                'Credit deduction logic based on per-country price ranges.',
                'Inbound and outbound call handling with dynamic TwiML responses.',
            ],
            'start_date' => '2024-10-01', 'end_date' => '2025-04-30', 'live_url' => 'https://app.dialinger.com/',
            'is_featured' => true, 'sort_order' => 2, 'status' => 'published',
            'source_note' => 'CV only (not covered by the available transcripts).',
        ]);
        $dialinger->syncTechnologies(['Laravel', 'PHP', 'Twilio', 'Stripe', 'TwiML']);

        $school = Project::create([
            'name' => 'School Management System', 'slug' => 'school-management-system',
            'tagline' => 'Student lifecycle and fee management for schools.',
            'features' => [
                'Student lifecycle management: creation, updates, validation and guardian associations.',
                'Monthly fee schedules per student, with payments and bill/invoice generation.',
            ],
            'start_date' => '2025-09-01', 'is_current' => true, 'live_url' => 'https://school-accounting.vercel.app/',
            'sort_order' => 3, 'status' => 'published',
            'source_note' => 'CV only. Tech stack and employer/client are not stated anywhere — add them in the admin.',
        ]);

        return ['hajir' => $hajir, 'dialinger' => $dialinger, 'school' => $school];
    }

    private function achievements(Project $hajir): void
    {
        $highlights = [
            ['payroll', 'Payroll · Core', 'Payroll engine, end to end', 'Fiscal years, income heads, salary revisions, ad-hoc payments, generation → approval → release workflow, payslips and payback summaries.', [
                'Multi-level approval flow with comments and approver lookup.',
                'Payroll frequency support (monthly, semi-monthly, multiple runs per month) with per-period divisors and re-run merging.',
                'Income-head-wise payroll, held and carried-forward earnings, and partial runs up to a date.',
                'Dynamic payroll columns, release tables, payslip configuration and PDF payslips.',
            ]],
            ['tax', 'Tax · Compliance', 'Nepal income-tax engine', 'A projection-based TDS engine that spreads annual tax across the fiscal year and handles everything that happens mid-year.', [
                'Per-company formula switch: dynamic, current-month and cumulative (earned-till-now).',
                'Section 63 retirement cap — min(contribution, Rs 5 lakh, ⅓ of assessable income) across SSF/CIT/PF groups.',
                'SST vs. remuneration-tax split, and employer contributions in assessable income.',
                'Lump-sum tax at payment for ad-hoc bonuses; mid-year opening configuration with YTD snapshots.',
            ]],
            ['payroll', 'New module', 'Academic class-based pay', 'A Laravel module for schools: teachers paid per class conducted, flowing through attendance, payroll, tax and payslips.', [
                'Module-owned tables only — no columns added to core tables.',
                'Effective-dated class rates per salary revision; branch-inherited settings.',
                'Shift-assignment guard wired into every assignment path (bulk, Excel, onboarding).',
                'Nightly calculation jobs and a Next.js UI for rates, settings and overrides.',
            ]],
            ['payroll', 'Payroll · Finance', 'Advance salary & payback', 'Advance salary and payback with accurate remaining-balance calculation — later extended to many concurrent advances per employee.', [
                'Instalments deducted from every active advance, with a cap across runs.',
                'Partial carry-over write-offs settled correctly; repaid advances marked paid on release.',
            ]],
            ['payroll', 'Lifecycle', 'Offboarding & gratuity settlement', 'Final-run settlement for leaving employees: accrued gratuity and pro-rated pay in one payroll, plus self-service SSF/tax contribution reporting.', [
                'Settlement aware of partial runs and offboarding dates.',
                'Mid-year employee transfers carry YTD tax and contribution snapshots.',
            ]],
            ['tax', 'Reports', 'Statutory reports & letters', "SSF collection and TDS transaction exports in the government portals' formats, plus bank and contribution letters.", [
                'Filed-months-only rule; avoided a join that silently multiplied amounts.',
                'Word bank letters, PDF benefit-contribution letters and Excel exports.',
            ]],
            ['workforce', 'Workforce', 'Shifts, overtime, leave & attendance', 'Hundreds of commits extending and maintaining the daily machinery that payroll depends on.', [
                'Shift conflict checker and chunked bulk shift assignment.',
                'Overtime requests with approval, ratios and salary-source tracking.',
                'Sandwich-leave deduction, forced leave, half-day and flexible leave, holiday adjustments.',
                'Daily, weekly and monthly attendance reports.',
            ]],
            ['platform', 'Platform', 'Calendar switch (BS ⇄ AD)', 'Let a company change its date system mid-life without corrupting history.', [
                'History table plus a piecewise timeline resolver that answers "which calendar was this date in?".',
                'Seam validation and per-date label resolution across reports and payroll.',
            ]],
            ['platform', 'Security', 'Supervisor-scoped access', 'A branch + department floor so sub-employers only see and change the employees they manage.', [
                'Shared scope resolver adopted by the generate and release controllers.',
                'Write guards on release sections, with feature tests.',
            ]],
            ['platform', 'SaaS', 'Subscriptions & onboarding', 'Billing cycles, trials and a step-based company onboarding flow.', []],
            ['debug', 'Bug hunt', 'The month that disappeared', 'Annual reports dropped a month for Gregorian-calendar companies because buckets were Bikram Sambat–anchored. Also found overlapping fiscal-year rows that doubled payable days.', []],
        ];
        foreach ($highlights as $i => [$cat, $label, $title, $summary, $details]) {
            Achievement::create([
                'project_id' => $hajir->id, 'kind' => 'highlight', 'category' => $cat, 'label' => $label, 'title' => $title,
                'summary' => $summary, 'details' => $details ?: null, 'sort_order' => $i + 1, 'status' => 'published',
                'source_note' => 'MEM notes + GIT history for the Hajir codebase (previous portfolio version).',
            ]);
        }

        Achievement::create([
            'project_id' => $hajir->id, 'kind' => 'highlight', 'category' => 'debug', 'label' => 'Bug hunt', 'title' => 'Earned > monthly salary',
            'summary' => 'Traced inflated pay to a per-day divisor going stale when shifts changed mid-month. Fixed by recalculating the whole month on any shift adjustment — and sized the backfill at 556 affected candidate-months.',
            'sort_order' => count($highlights) + 1, 'status' => 'needs_review',
            'source_note' => 'MEM working-days-daily-salary-divisor-drift. Needs review: "556 candidate-months" is an internal production figure — confirm it is OK to publish.',
        ]);

        $milestones = [
            ['2019 – 2024', "Bachelor's in Information Technology", 'Patan Multiple Campus, Lalitpur.'],
            ['Jun – Oct 2024', 'Backend Developer Intern — Times Global', 'Laravel Blade templates for data tables and admin workflows; backend features and production bug fixes.'],
            ['Oct 2024', 'Software Developer — Velox Labs', 'Started on the Laravel backend of Hajir and on Dialinger, a Twilio + Stripe cloud telephony platform.'],
            ['Apr 2025', 'Dialinger shipped', 'Calls, SMS/MMS, analytics, subscriptions, KYC and per-country credit deduction.'],
            ['Mid 2025', 'Attendance, leave & shift reports', 'Leave years, attendance charts, remote areas, planned vs. worked time per shift.'],
            ['Jul – Sep 2025', 'Payroll from the ground up', 'Fiscal years, income heads, tax slabs, tax benefits, ad-hoc payments, payroll store, approval and release.'],
            ['Sept 2025', 'School management system', 'Student lifecycle, guardians, monthly fee schedules, payments and invoices.'],
            ['Late 2025', 'Letters, penalties & employee payroll', 'Bank letters, benefit-contribution letters, deductions and employee-side payroll APIs.'],
            ['Early 2026', 'SaaS & leave policy', 'Subscriptions, trials, onboarding steps, forced leave and policy validation.'],
            ['Mid 2026', 'Tax engine & compliance', 'Dynamic tax, SSF/TDS reports, sandwich leave in payroll, offboarding & gratuity, calendar switch.'],
            ['Oct 2026', 'Academic pay & full-stack', 'Shipped the class-pay module across backend and Next.js, per-company tax formulas, retirement caps and multiple advances.'],
        ];
        foreach ($milestones as $i => [$when, $title, $summary]) {
            Achievement::create([
                'kind' => 'milestone', 'date_label' => $when, 'title' => $title, 'summary' => $summary,
                'sort_order' => $i + 1, 'status' => 'published', 'source_note' => 'CV dates + GIT/MEM for the Hajir timeline.',
            ]);
        }
    }

    private function skills(): void
    {
        // Levels only where the CV claims "strong experience"; everything else is left unrated for the owner to set.
        $groups = [
            ['Backend', 'Server-side development', [['Laravel', 3], ['PHP', 3], ['REST APIs', 3], ['Authentication (Sanctum, Passport)', null], ['WebSockets', null], ['Queues & scheduled jobs', null]],
                'CV skills + objective ("strong experience in Laravel, REST API development"). GIT/MEM for Sanctum/Passport and queues.'],
            ['Databases', 'Relational design and performance', [['MySQL', 3], ['Relational database design', 3], ['Query optimization', null], ['PostgreSQL', null], ['SQL', null]],
                'CV skills + objective ("relational database design", "improving query performance").'],
            ['Integrations', 'Payments, telephony and messaging', [['Stripe', null], ['Twilio', null], ['Payment gateways', null], ['SMS / telephony APIs', null]],
                'CV skills (Dialinger).'],
            ['Frontend', 'For the features I ship end to end', [['JavaScript', null], ['Next.js', null], ['React', null], ['MUI', null], ['Redux Toolkit', null]],
                'CV (JavaScript). GIT: 550 commits to the Next.js dashboard (Next.js 14, React 18, MUI 5, Redux Toolkit).'],
            ['Tools', 'Day-to-day tooling', [['Git', null], ['Linux', null], ['Postman', null], ['PHPUnit', null], ['Docker', null]],
                'CV (Git, Linux, Postman). MEM/GIT for PHPUnit and Docker.'],
        ];
        foreach ($groups as $i => [$name, $desc, $skills, $note]) {
            $category = SkillCategory::create(['name' => $name, 'description' => $desc, 'sort_order' => $i + 1, 'status' => 'published', 'source_note' => $note]);
            foreach ($skills as $j => [$skill, $level]) {
                Skill::create(['skill_category_id' => $category->id, 'name' => $skill, 'level' => $level, 'sort_order' => $j + 1, 'status' => 'published', 'source_note' => $note]);
            }
        }
    }

    private function expertise(): void
    {
        foreach ([
            ['Backend development', 'Laravel services, jobs and modules that model money, time and rules — built so the next change is cheap.'],
            ['REST API design', 'Versioned, validated JSON APIs consumed by web dashboards and mobile apps.'],
            ['Database design & performance', 'Relational schemas, safe data migrations, and finding the query that makes a page slow.'],
            ['Third-party integrations', 'Stripe subscriptions, Twilio calls and SMS with TwiML, KYC and payment-gateway flows.'],
            ['Payroll & tax domain', 'Nepal income tax (TDS), SSF/CIT/PF, gratuity, leave and attendance rules turned into tested code.'],
            ['Root-cause debugging', 'Tracing production discrepancies to the shared code path and fixing them once, with a test.'],
        ] as $i => [$title, $desc]) {
            ExpertiseArea::create(['title' => $title, 'description' => $desc, 'sort_order' => $i + 1, 'status' => 'published', 'source_note' => 'CV objective/skills + MEM notes.']);
        }
    }

    private function education(): void
    {
        Education::create([
            'institution' => 'Patan Multiple Campus', 'degree' => "Bachelor's in Information Technology", 'location' => 'Lalitpur, Nepal',
            'start_year' => 2019, 'end_year' => 2024, 'sort_order' => 1, 'status' => 'published', 'source_note' => 'CV.',
        ]);
        // No certifications found in the CV or the transcripts.
    }

    private function links(): void
    {
        SocialLink::create(['platform' => 'github', 'label' => 'GitHub', 'url' => 'https://github.com/shakyarishesh', 'sort_order' => 1, 'status' => 'published', 'source_note' => 'CV hyperlink.']);
        SocialLink::create(['platform' => 'linkedin', 'label' => 'LinkedIn', 'url' => 'https://np.linkedin.com/in/risheshakya7', 'sort_order' => 2, 'status' => 'published', 'source_note' => 'CV hyperlink.']);
    }
}
