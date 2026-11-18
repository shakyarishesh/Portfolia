{{-- Decorative emoji for a free-text name (skill category, project category, link platform). Usage: @include('portfolios.templates.picto._emoji', ['for' => $name, 'fallback' => '✨']) --}}
@php
    $name = mb_strtolower((string) ($for ?? ''));
    $emoji = $fallback ?? '✨';
    foreach ([
        'github' => '🐙', 'gitlab' => '🦊', 'linkedin' => '💼', 'twitter' => '🐦', 'mastodon' => '🐘', 'youtube' => '📺',
        'instagram' => '📸', 'dribbble' => '🏀', 'behance' => '🎨', 'medium' => '📰', 'stack' => '📚', 'website' => '🌐',
        'backend' => '⚙️', 'server' => '⚙️', 'api' => '🔌', 'integration' => '🔌', 'database' => '🗄️', 'data' => '📊',
        'frontend' => '🎨', 'ui' => '🎨', 'design' => '✏️', 'mobile' => '📱', 'app' => '📱', 'devops' => '☁️', 'cloud' => '☁️',
        'tool' => '🛠️', 'test' => '🧪', 'security' => '🔒', 'ai' => '🤖', 'machine' => '🤖', 'language' => '🗣️',
        'soft' => '🤝', 'team' => '🤝', 'saas' => '🧩', 'web' => '🌐', 'game' => '🎮', 'open source' => '🌱', 'library' => '📦',
    ] as $needle => $icon) {
        if ($name !== '' && preg_match('/\b'.preg_quote($needle, '/').(strlen($needle) <= 3 ? 's?\b' : '').'/u', $name)) { $emoji = $icon; break; }
    }
@endphp
{{ $emoji }}
