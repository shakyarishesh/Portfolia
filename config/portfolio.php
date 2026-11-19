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
