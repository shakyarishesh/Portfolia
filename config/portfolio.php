<?php

return [

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
