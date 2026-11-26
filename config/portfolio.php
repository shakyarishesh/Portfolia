<?php

return [

    // Name of the platform itself (landing page, /create, demo) — not a person's portfolio.
    'platform_name' => env('PLATFORM_NAME', 'Portfolia'),

    // Portfolio served at "/" (legacy single-site URL). Empty = the oldest published portfolio.
    'default_username' => env('PORTFOLIO_DEFAULT_USERNAME'),

    /*
     | Template registry (App\Support\Templates). Add a template: add an entry here and create
     | resources/views/portfolios/templates/{key}/{home,project,posts,post}.blade.php + public/templates/{key}/.
     | Settings are an allow-list: type boolean|select|text|textarea; nothing outside it is stored or rendered.
     */
    'templates' => [
        'classic' => [
            'name' => 'Classic',
            'description' => 'The original portfolio: tabbed single page, animated background, cursor effects and light/dark themes.',
            'preview' => 'templates/classic/preview.svg',
            'active' => true,
            'version' => 1,
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog'],
            'settings' => [],
            'shared_defaults' => ['cursor' => 'trail'],
        ],
        'minimal_editorial' => [
            'name' => 'Minimal Editorial',
            'description' => 'Typography-first engineer portfolio: sticky identity column, text-led experience and project lists, restrained motion.',
            'preview' => 'templates/minimal_editorial/preview.svg',
            'reference' => 'https://brittanychiang.com/',
            'active' => true,
            'version' => 1,
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog'],
            'settings' => [
                'accent' => ['type' => 'select', 'label' => 'Accent colour', 'default' => 'teal',
                    'options' => ['teal' => 'Teal', 'amber' => 'Amber', 'violet' => 'Violet', 'rose' => 'Rose']],
                'theme' => ['type' => 'select', 'label' => 'Colour scheme', 'default' => 'dark',
                    'options' => ['dark' => 'Dark', 'light' => 'Light']],
                'show_thumbnails' => ['type' => 'boolean', 'label' => 'Show project thumbnails', 'default' => true],
            ],
        ],
        'content_journal' => [
            'name' => 'Content Journal',
            'description' => 'Content-first knowledge hub: short bio, evergreen notes, dated articles and projects, with light/dark mode.',
            'preview' => 'templates/content_journal/preview.svg',
            'reference' => 'https://leerob.com/',
            'active' => true,
            'version' => 1,
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog', 'notes'],
            'settings' => [
                'theme' => ['type' => 'select', 'label' => 'Default colour scheme', 'default' => 'system',
                    'options' => ['system' => 'Follow visitor\'s system', 'light' => 'Light', 'dark' => 'Dark']],
                'now' => ['type' => 'textarea', 'label' => '"Now" — what you are focused on (Markdown, optional)', 'max' => 2000, 'default' => null],
                'uses' => ['type' => 'textarea', 'label' => '"Uses" — your tools and setup (Markdown, optional)', 'max' => 2000, 'default' => null],
                'home_posts' => ['type' => 'select', 'label' => 'Articles on the home page', 'default' => '5',
                    'options' => ['3' => '3', '5' => '5', '10' => '10']],
            ],
        ],
        'creative_experience' => [
            'name' => 'Creative Experience',
            'description' => 'Playful, motion-rich portfolio with an interactive canvas hero and showcase project layouts; static fallback for reduced motion.',
            'preview' => 'templates/creative_experience/preview.svg',
            'reference' => 'https://bruno-simon.com/',
            'active' => true,
            'version' => 1,
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog'],
            'settings' => [
                'palette' => ['type' => 'select', 'label' => 'Palette', 'default' => 'sunset',
                    'options' => ['sunset' => 'Sunset', 'ocean' => 'Ocean', 'mono' => 'Monochrome']],
                'hero' => ['type' => 'select', 'label' => 'Hero interaction', 'default' => 'particles',
                    'options' => ['particles' => 'Particle field', 'orbits' => 'Orbiting skills', 'none' => 'Static']],
                'motion' => ['type' => 'boolean', 'label' => 'Enable motion effects', 'default' => true],
            ],
            'shared_defaults' => ['cursor' => 'trail'],
        ],
        'professional' => [
            'name' => 'Professional',
            'description' => 'Polished light layout for engineers, consultants and job seekers: sticky navbar with Contact CTA, timeline, project cards.',
            'preview' => 'templates/professional/preview.svg',
            'reference' => 'https://myseera.com/demo/professional-template',
            'active' => true,
            'version' => 1,
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog'],
            'settings' => [
                'accent' => ['type' => 'select', 'label' => 'Accent colour', 'default' => 'navy',
                    'options' => ['navy' => 'Navy', 'teal' => 'Teal', 'slate' => 'Slate', 'burgundy' => 'Burgundy']],
                'show_stats' => ['type' => 'boolean', 'label' => 'Show highlight stats in About (years, projects, skills)', 'default' => true],
            ],
        ],
        'picto' => [
            'name' => 'Picto Portfolio',
            'description' => 'Friendly, colourful and visual: icon navigation with scroll-spy, emoji section markers, skill tiles and sticker-like cards.',
            'preview' => 'templates/picto/preview.svg',
            'reference' => 'https://myseera.com/demo/picto-portfolio',
            'active' => true,
            'version' => 1,
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog'],
            'settings' => [
                'palette' => ['type' => 'select', 'label' => 'Palette', 'default' => 'candy',
                    'options' => ['candy' => 'Candy', 'tropical' => 'Tropical', 'sunrise' => 'Sunrise']],
                'decorations' => ['type' => 'boolean', 'label' => 'Floating emoji decorations in the hero', 'default' => true],
            ],
        ],
        'gradient' => [
            'name' => 'Gradient',
            'description' => 'Vibrant, light layout where tasteful gradients mark the focal points: hero phrase, buttons, timeline, card edges.',
            'preview' => 'templates/gradient/preview.svg',
            'reference' => 'https://myseera.com/demo/gradient-design',
            'active' => true,
            'version' => 1,
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog'],
            'settings' => [
                'gradient' => ['type' => 'select', 'label' => 'Gradient', 'default' => 'blue_violet',
                    'options' => ['blue_violet' => 'Blue → violet', 'teal_blue' => 'Teal → blue', 'coral_purple' => 'Coral → purple']],
                'shapes' => ['type' => 'boolean', 'label' => 'Abstract gradient shapes in the hero', 'default' => true],
            ],
        ],
        'modern_personal' => [
            'name' => 'Modern Personal Website',
            'description' => 'Calm, typography-first personal site: selected work as rows, compact experience, simple writing list, minimal decoration.',
            'preview' => 'templates/modern_personal/preview.svg',
            'reference' => 'https://myseera.com/demo/modern-personal-website',
            'active' => true,
            'version' => 1,
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog'],
            'settings' => [
                'accent' => ['type' => 'select', 'label' => 'Accent colour', 'default' => 'ink',
                    'options' => ['ink' => 'Ink blue', 'olive' => 'Olive', 'terracotta' => 'Terracotta']],
                'serif_headings' => ['type' => 'boolean', 'label' => 'Serif headings', 'default' => true],
                'featured_count' => ['type' => 'select', 'label' => 'Projects under "Selected work"', 'default' => '4',
                    'options' => ['3' => '3', '4' => '4', '6' => '6']],
            ],
        ],
    ],

    // Settings every template has, edited with the template's own settings but stored once per portfolio
    // (template_settings._shared) so they survive a template switch.
    'shared_settings' => [
        'intro' => ['type' => 'boolean', 'label' => 'Welcome intro: "welcome" in several languages on a visitor\'s first visit', 'default' => true],
        'intro_words' => ['type' => 'textarea', 'label' => 'Welcome words, one per line (blank = नमस्ते, Bienvenido, Bienvenue … Welcome)', 'max' => 600, 'default' => null],
        // Templates can change these defaults with 'shared_defaults'.
        'cursor' => ['type' => 'select', 'label' => 'Cursor effect (mouse and trackpad only)', 'default' => 'none',
            'options' => ['trail' => 'Glowing trail', 'ring' => 'Neon ring & dot (Classic template only)', 'none' => 'Off']],
        'cursor_color' => ['type' => 'select', 'label' => 'Cursor trail colour', 'default' => 'electric',
            'options' => ['electric' => 'Electric blue', 'violet' => 'Violet', 'pink' => 'Pink', 'green' => 'Green', 'amber' => 'Amber']],
    ],
    // Default welcome words, shown in this order (the last one stays until the screen lifts).
    'intro_words' => ['नमस्ते', 'Bienvenido', 'Bienvenue', 'Willkommen', 'Benvenuto', 'ようこそ', '환영합니다', '欢迎', 'Bem-vindo', 'Welcome'],

    /*
     | Portfolio types. These are the DEFAULTS: the migration copies them into the portfolio_types table, where admins
     | edit name, description, icon, categories, modules, default sections and compatible templates (Admin → Portfolio
     | types). Add a type here (and run a migration that inserts it) or create it from the admin screen.
     | 'sample' (not stored) is the content of the "See a sample" demo on /create.
     */
    'types' => [
        'technical' => [
            'name' => 'Technical & IT', 'icon' => '💻',
            'description' => 'For people who design, build, test and run software, data and infrastructure.',
            'example' => 'Case studies with the problem, architecture, code repository and live app.',
            'categories' => ['Software developers', 'Backend and frontend developers', 'Full-stack developers', 'QA engineers and testers',
                'DevOps engineers', 'Data analysts', 'Cybersecurity professionals', 'IT professionals'],
            'modules' => ['technical'],
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog', 'notes'],
            'templates' => ['classic', 'minimal_editorial', 'professional', 'gradient', 'modern_personal', 'creative_experience'],
            'sample' => ['name' => 'Asha Karki', 'headline' => 'Full-stack developer', 'skills' => 'Laravel, PHP, React, TypeScript, MySQL, Docker',
                'bio' => 'I build fast, reliable web applications — from database design to the last pixel of the UI.',
                'projects' => [
                    ['name' => 'Clinic booking platform', 'description' => 'Online booking and reminders for 40 clinics; cut no-shows by a third.', 'technologies' => 'Laravel, Vue, MySQL, Redis',
                        'details' => ['problem' => 'Phone-only bookings overloaded reception and patients often forgot appointments.', 'outcome' => 'No-shows fell by 34% in the first quarter.']],
                    ['name' => 'Payments API', 'description' => 'A single API over three local payment gateways.', 'technologies' => 'PHP, REST, Docker'],
                ]],
        ],
        'creative' => [
            'name' => 'Creative & Design', 'icon' => '🎨',
            'description' => 'For designers, photographers, illustrators, video makers, artists and content creators.',
            'example' => 'Visual galleries and case studies: brief, creative process and final result.',
            'categories' => ['Graphic designers', 'UI/UX designers', 'Product designers', 'Photographers', 'Illustrators', 'Video editors', 'Artists', 'Content creators'],
            'modules' => ['creative'],
            'sections' => ['home', 'projects', 'approach', 'experience', 'skills', 'education', 'contact'],
            'templates' => ['picto', 'creative_experience', 'gradient', 'modern_personal', 'minimal_editorial'],
            'sample' => ['name' => 'Maya Gurung', 'headline' => 'Brand & UI designer', 'skills' => 'Figma, Illustrator, Photoshop, After Effects, Branding',
                'bio' => 'I design identities and interfaces that people remember — and enjoy using.',
                'projects' => [
                    ['name' => 'Himal Coffee rebrand', 'description' => 'New identity, packaging and website for a specialty roaster.', 'technologies' => 'Illustrator, Figma',
                        'details' => ['client' => 'Himal Coffee Co.', 'process' => 'Workshops with the founders, three moodboards, then a mark drawn from the roaster\'s mountain logo.', 'outcome' => 'Online sales doubled in six months.']],
                    ['name' => 'Travel app UI', 'description' => 'Booking flow redesign for a travel startup.', 'technologies' => 'Figma, Protopie'],
                ]],
        ],
        'business' => [
            'name' => 'Business & Marketing', 'icon' => '📈',
            'description' => 'For marketers, sales people, analysts, consultants, PR specialists and entrepreneurs.',
            'example' => 'Campaigns and initiatives with the strategy, execution and measured results.',
            'categories' => ['Digital marketers', 'Marketing specialists', 'Sales professionals', 'Business analysts', 'Entrepreneurs', 'Business consultants', 'Public relations professionals'],
            'modules' => ['business'],
            'sections' => ['home', 'projects', 'experience', 'skills', 'services', 'education', 'contact'],
            'templates' => ['professional', 'minimal_editorial', 'modern_personal', 'gradient'],
            'sample' => ['name' => 'Rohan Shrestha', 'headline' => 'Growth marketer', 'skills' => 'SEO, Google Ads, Meta Ads, HubSpot, Analytics, Copywriting',
                'bio' => 'I plan and run campaigns that turn attention into revenue — and I measure every step.',
                'projects' => [
                    ['name' => 'Festive season campaign', 'description' => 'Multi-channel campaign for an online retailer.', 'technologies' => 'Meta Ads, Google Ads, Mailchimp',
                        'details' => ['industry' => 'E-commerce', 'strategy' => 'Retarget past buyers first, then lookalike audiences.', 'metrics' => ['Revenue growth: +62%', 'Return on ad spend: 5.4x', 'Email open rate: 41%']]],
                    ['name' => 'B2B lead funnel', 'description' => 'Content and webinar funnel for a SaaS company.', 'technologies' => 'HubSpot, LinkedIn Ads'],
                ]],
        ],
        'management' => [
            'name' => 'Management & Leadership', 'icon' => '🧭',
            'description' => 'For project, product, operations and HR managers, team leads and coordinators.',
            'example' => 'Initiatives you led: the team, the strategy and the measurable impact.',
            'categories' => ['Project managers', 'Product managers', 'HR professionals', 'Operations managers', 'Team leaders', 'Administrators', 'Program coordinators'],
            'modules' => ['management'],
            'sections' => ['home', 'leadership', 'experience', 'projects', 'skills', 'education', 'contact'],
            'templates' => ['professional', 'minimal_editorial', 'modern_personal'],
            'sample' => ['name' => 'Sita Thapa', 'headline' => 'Operations manager', 'skills' => 'Agile, Lean, Budgeting, Hiring, Stakeholder management, Jira',
                'bio' => 'I build calm, effective teams and processes that scale.',
                'projects' => [
                    ['name' => 'Warehouse process overhaul', 'description' => 'Re-designed picking and packing across two warehouses.', 'technologies' => 'Lean, Kanban',
                        'details' => ['team_size' => 35, 'outcome' => 'Order turnaround went from 2 days to same-day.', 'impact' => 'Saved roughly 18% in operating costs per year.']],
                    ['name' => 'New HR system roll-out', 'description' => 'Moved 400 staff to a new HR and payroll system.', 'technologies' => 'Change management, Prince2'],
                ]],
        ],
        'education' => [
            'name' => 'Education & Training', 'icon' => '🎓',
            'description' => 'For teachers, lecturers, researchers, trainers and instructional designers.',
            'example' => 'Courses and programs with objectives, methodology and learning outcomes; publications.',
            'categories' => ['Teachers', 'Lecturers', 'Academic researchers', 'Trainers', 'Educational consultants', 'Instructional designers'],
            'modules' => ['education'],
            'sections' => ['home', 'philosophy', 'experience', 'projects', 'publications', 'education', 'skills', 'contact'],
            'templates' => ['professional', 'minimal_editorial', 'content_journal', 'modern_personal'],
            'sample' => ['name' => 'Dr. Anil Joshi', 'headline' => 'Lecturer in Environmental Science', 'skills' => 'Curriculum design, Research methods, Moodle, Field studies',
                'bio' => 'I teach students to read the landscape around them — and to ask better questions about it.',
                'projects' => [
                    ['name' => 'Field Ecology 201', 'description' => 'A second-year course built around four field trips.', 'technologies' => 'Moodle, GIS',
                        'details' => ['subject' => 'Ecology', 'audience' => 'Second-year undergraduates', 'outcome' => '92% pass rate; two student papers published.']],
                    ['name' => 'Teacher training workshop', 'description' => 'Hands-on climate education for 60 school teachers.', 'technologies' => 'Workshops'],
                ]],
        ],
        'general' => [
            'name' => 'General Professional', 'icon' => '💼',
            'description' => 'For any other profession, recent graduates, career changers and freelancers.',
            'example' => 'Your career story, core strengths and a few selected pieces of work.',
            'categories' => ['Professionals from other industries', 'Recent graduates', 'Career changers', 'Freelancers', 'Individuals with diverse professional backgrounds'],
            'modules' => ['general'],
            'sections' => ['home', 'experience', 'projects', 'skills', 'services', 'education', 'contact'],
            'templates' => ['professional', 'classic', 'minimal_editorial', 'modern_personal'],
            'sample' => ['name' => 'Priya Rai', 'headline' => 'Freelance translator & editor', 'skills' => 'Translation, Editing, Proofreading, Nepali, English, Hindi',
                'bio' => 'I help organisations say what they mean — clearly, in three languages.',
                'projects' => [
                    ['name' => 'NGO annual report', 'description' => 'Translated and edited a 120-page annual report.', 'technologies' => 'Trados',
                        'details' => ['client' => 'A health NGO', 'outcome' => 'Delivered two weeks early, praised by the board.']],
                    ['name' => 'Tourism website copy', 'description' => 'Website copy in three languages for a trekking agency.', 'technologies' => 'WordPress'],
                ]],
        ],
        'hybrid' => [
            'name' => 'Hybrid Portfolio', 'icon' => '🧩',
            'description' => 'For people with more than one discipline: combine the sections and project fields of each.',
            'example' => 'e.g. Developer + Designer, Teacher + Researcher, Marketing + Management.',
            'categories' => ['Developer + Designer', 'Teacher + Researcher', 'Marketing + Business Management', 'Photographer + Content Creator', 'Project Manager + Business Analyst'],
            'modules' => [], // the modules of the disciplines the person picks
            'sections' => ['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog'],
            'templates' => ['professional', 'modern_personal', 'gradient', 'creative_experience'],
            'sample' => ['name' => 'Kiran Lama', 'headline' => 'Developer & designer', 'skills' => 'Figma, React, TypeScript, Illustration, Node.js',
                'bio' => 'I design products and then build them — the same person on both sides of the hand-off.',
                'disciplines' => ['technical', 'creative'],
                'projects' => [
                    ['name' => 'Recipe app', 'description' => 'Designed and built a recipe app from sketch to store.', 'technologies' => 'Figma, React Native',
                        'details' => ['process' => 'Paper sketches, a clickable prototype, five user tests, then the build.', 'disciplines' => ['technical', 'creative']]],
                    ['name' => 'Studio website', 'description' => 'Brand, illustrations and site for an architecture studio.', 'technologies' => 'Illustrator, Astro'],
                ]],
        ],
    ],

    /*
     | Profession-specific modules. A portfolio's modules come from its type (or, for Hybrid, from each discipline).
     | 'work'        → labels of the projects section/list: [tab, small heading, title, one item].
     | 'skills'      → labels of the skills section.
     | 'project_fields' → extra project fields shown in the project form, field => label (null = the field's own label).
     |   Fields are project columns (problem, features …) or entries of 'project_details' below (stored in projects.details).
     | 'labels'      → new labels for the always-present project fields (role, live_url …).
     */
    'modules' => [
        'technical' => [
            'name' => 'Technical case studies',
            'description' => 'Problem, solution, architecture, challenges, diagram, repository and live links, technologies used.',
            'work' => ['Projects', 'Selected work', 'Things I have built', 'project'],
            'skills' => ['Skills', 'Toolbox', 'What I work with'],
            'project_fields' => ['problem' => null, 'features' => null, 'architecture' => null, 'challenges' => null, 'diagram' => null,
                'outcome' => 'Outcomes', 'repo_url' => null, 'technologies' => null],
            'labels' => [],
        ],
        'creative' => [
            'name' => 'Visual work & galleries',
            'description' => 'Client, objective, creative process, tools, contribution, final outcome, video and image gallery.',
            'work' => ['Work', 'Portfolio', 'Selected work', 'work sample'],
            'skills' => ['Tools', 'Toolbox', 'Creative tools & specialties'],
            'project_fields' => ['client' => 'Client or organization', 'problem' => 'Project objective', 'process' => null, 'outcome' => 'Final outcome',
                'video_url' => null, 'technologies' => 'Tools used'],
            'labels' => ['name' => 'Project title', 'role' => 'My contribution', 'live_url' => 'External project URL', 'category' => 'Category (e.g. Branding, Photography, UI/UX)'],
        ],
        'business' => [
            'name' => 'Campaigns & results',
            'description' => 'Industry, client, objective, audience, strategy, execution, tools, private budget, results and performance metrics.',
            'work' => ['Campaigns', 'Campaigns & projects', 'Work that moved the numbers', 'campaign'],
            'skills' => ['Expertise', 'Expertise', 'Business & marketing skills'],
            'project_fields' => ['industry' => null, 'client' => 'Organization / client', 'problem' => 'Objective', 'audience' => null, 'strategy' => null,
                'execution' => null, 'outcome' => 'Results', 'metrics' => null, 'budget' => null, 'technologies' => 'Tools and platforms'],
            'labels' => ['name' => 'Project or campaign name', 'role' => 'My role'],
        ],
        'management' => [
            'name' => 'Leadership initiatives',
            'description' => 'Organization, objective, team size, responsibilities, challenges, strategy, execution, methodologies, results and impact.',
            'work' => ['Initiatives', 'Projects led', 'Initiatives I have led', 'initiative'],
            'skills' => ['Expertise', 'Expertise', 'Management expertise'],
            'project_fields' => ['client' => 'Organization', 'problem' => 'Objective', 'team_size' => null, 'responsibilities' => null, 'challenges' => 'Challenges',
                'strategy' => null, 'execution' => null, 'outcome' => 'Results', 'impact' => null, 'metrics' => 'Key numbers', 'technologies' => 'Tools and methodologies'],
            'labels' => ['name' => 'Initiative name'],
        ],
        'education' => [
            'name' => 'Courses & teaching',
            'description' => 'Subject, institution, audience, learning objectives, methodology, materials, contribution and learning outcomes.',
            'work' => ['Teaching', 'Courses & programs', 'What I teach', 'course or program'],
            'skills' => ['Expertise', 'Expertise', 'Subjects & areas of expertise'],
            'project_fields' => ['subject' => null, 'client' => 'Institution', 'audience' => null, 'problem' => 'Learning objectives', 'methodology' => null,
                'materials' => null, 'outcome' => 'Learning outcomes', 'video_url' => 'Video or recording URL', 'technologies' => 'Platforms and tools'],
            'labels' => ['name' => 'Course or program title', 'role' => 'My contribution'],
        ],
        'general' => [
            'name' => 'Work samples',
            'description' => 'Flexible work samples: client or organization, my contribution, outcome and links.',
            'work' => ['Work', 'Selected work', 'Work samples', 'work sample'],
            'skills' => ['Skills', 'Core competencies', 'What I do well'],
            'project_fields' => ['client' => 'Client or organization', 'outcome' => 'Outcome', 'technologies' => 'Skills and tools used'],
            'labels' => ['role' => 'My contribution', 'live_url' => 'Link'],
        ],
    ],

    // Profession-specific project fields, stored in projects.details (JSON). Types: text, textarea, url, number, lines.
    'project_details' => [
        'client' => ['type' => 'text', 'label' => 'Client or organization', 'max' => 150],
        'industry' => ['type' => 'text', 'label' => 'Industry', 'max' => 100],
        'subject' => ['type' => 'text', 'label' => 'Subject', 'max' => 150],
        'audience' => ['type' => 'text', 'label' => 'Target audience', 'max' => 255],
        'team_size' => ['type' => 'number', 'label' => 'Team size', 'help' => 'Optional.'],
        'responsibilities' => ['type' => 'lines', 'label' => 'Responsibilities', 'help' => 'One per line.'],
        'process' => ['type' => 'textarea', 'label' => 'Creative process'],
        'strategy' => ['type' => 'textarea', 'label' => 'Strategy'],
        'execution' => ['type' => 'textarea', 'label' => 'Execution'],
        'methodology' => ['type' => 'textarea', 'label' => 'Teaching methodology'],
        'materials' => ['type' => 'textarea', 'label' => 'Materials and resources'],
        'outcome' => ['type' => 'textarea', 'label' => 'Outcome'],
        'impact' => ['type' => 'textarea', 'label' => 'Business impact'],
        'metrics' => ['type' => 'lines', 'label' => 'Performance metrics', 'help' => 'One per line as "Label: value", e.g. "Conversion rate: 4.2%". Shown as figures; a percentage also gets a bar.'],
        'budget' => ['type' => 'text', 'label' => 'Budget (private)', 'max' => 100, 'private' => true, 'help' => 'Only for your records — never shown on the public portfolio.'],
        'video_url' => ['type' => 'url', 'label' => 'Video URL', 'help' => 'YouTube or Vimeo links are embedded; other links are shown as a link.'],
        // Hybrid portfolios only: which of the person's disciplines a project belongs to.
        'disciplines' => ['type' => 'checkboxes', 'label' => 'Disciplines'],
    ],

    // Sections beyond the built-in ones (Portfolio::DEFAULT_SECTIONS): free text (Markdown) written in Admin → Sections.
    // key => [tab, small heading, title]. A section with no text is not shown publicly.
    'extra_sections' => [
        'approach' => ['Approach', 'Creative process', 'How I work'],
        'leadership' => ['Leadership', 'Leadership', 'How I lead'],
        'philosophy' => ['Philosophy', 'Teaching philosophy', 'How I teach'],
        'publications' => ['Publications', 'Research', 'Publications & research'],
        'services' => ['Services', 'What I offer', 'Services'],
        'languages' => ['Languages', null, 'Languages'],
        'interests' => ['Interests', null, 'Professional interests'],
        'awards' => ['Awards', 'Recognition', 'Awards & achievements'],
    ],

    'uploads' => [
        'image_max_kb' => (int) env('PORTFOLIO_IMAGE_MAX_KB', 4096),
        'resume_max_kb' => (int) env('PORTFOLIO_RESUME_MAX_KB', 10240),
    ],

    'contact' => [
        // Email the owner when the contact form is used (needs a real MAIL_MAILER in production).
        'notify' => (bool) env('CONTACT_NOTIFY', true),
        // Defaults to the profile email.
        'notify_email' => env('CONTACT_NOTIFY_EMAIL'),
    ],

    // Public "Ask about me" assistant. Off by default because every question costs API credit.
    'assistant' => [
        'enabled' => (bool) env('PORTFOLIO_ASSISTANT', false),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    ],

    'import' => [
        // Upload limit for a conversation export (zip/json/jsonl/txt/md).
        'max_kb' => (int) env('PORTFOLIO_IMPORT_MAX_KB', 51200),
        // Stored per conversation; longer transcripts are truncated.
        'max_chars_per_conversation' => (int) env('PORTFOLIO_IMPORT_MAX_CHARS', 200000),
        'max_conversations' => (int) env('PORTFOLIO_IMPORT_MAX_CONVERSATIONS', 2000),
        // Conversations sent to the AI extractor in one run.
        'ai_max_conversations_per_run' => 10,
    ],

    'ai' => [
        // Leave AI_PROVIDER empty to use only the keyword scan and manual proposals.
        'provider' => env('AI_PROVIDER'),
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'max_input_chars' => (int) env('ANTHROPIC_MAX_INPUT_CHARS', 120000),
            'timeout' => (int) env('ANTHROPIC_TIMEOUT', 180),
        ],
    ],

    // Keyword scan: technology names to look for in YOUR messages, grouped by the
    // skill category they should be proposed under. Matching is whole-word, case-insensitive.
    'keywords' => [
        'Backend' => ['Laravel', 'PHP', 'Node.js', 'Express.js', 'Django', 'FastAPI', 'Flask', 'Spring Boot', 'Golang', 'Rust',
            'REST API', 'GraphQL', 'gRPC', 'WebSockets', 'Laravel Reverb', 'Laravel Octane', 'Sanctum', 'Passport', 'OAuth',
            'JWT', 'Horizon', 'Livewire', 'Inertia', 'Microservices'],
        'Databases' => ['MySQL', 'PostgreSQL', 'SQLite', 'MongoDB', 'Redis', 'Elasticsearch', 'MariaDB', 'DynamoDB'],
        'Frontend' => ['JavaScript', 'TypeScript', 'React', 'Next.js', 'Vue', 'Nuxt', 'Tailwind CSS', 'MUI', 'Redux Toolkit',
            'Alpine.js', 'three.js', 'Svelte'],
        'Integrations' => ['Stripe', 'Twilio', 'Firebase', 'FCM', 'Khalti', 'eSewa', 'PayPal', 'SendGrid', 'Mailgun', 'Pusher', 'Anthropic API', 'OpenAI API'],
        'Tools & DevOps' => ['Docker', 'Kubernetes', 'Git', 'GitHub Actions', 'Linux', 'Nginx', 'Apache', 'AWS', 'DigitalOcean',
            'Postman', 'PHPUnit', 'Pest', 'Jest', 'Playwright', 'Telescope', 'CI/CD'],
    ],

    // Keywords that are also ordinary words ("react to", "a pest") only match with this exact casing.
    'keywords_case_sensitive' => ['React', 'Rust', 'Pest', 'Jest', 'Passport', 'Horizon', 'Inertia', 'Telescope', 'Queues', 'Git', 'Go'],
];
