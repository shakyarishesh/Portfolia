@php
    use Illuminate\Support\Str;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $page = 'home';
    $deco = (bool) ($settings['decorations'] ?? true);
    $emoji = ['home' => '👋', 'experience' => '💼', 'projects' => '🚀', 'skills' => '🧰', 'education' => '🎓', 'contact' => '💌', 'writing' => '✏️'];
    $about = $profile->aboutParagraphs();
    $phone = $profile->show_phone && $profile->phone ? $profile->phone : null;

    // Only sections that will actually render; order and labels come from the admin. "Writing" slots in before Contact.
    $has = [
        'home' => $about || $expertise->isNotEmpty() || $testimonials->isNotEmpty(),
        'contact' => $profile->email || $phone || $profile->contact_form_enabled || $links->isNotEmpty(),
    ];
    $blocks = $sections->filter(fn ($s) => $has[$s->key] ?? true)->map(fn ($s) => [
        'key' => $s->key,
        'label' => $s->nav_label ?: Str::headline($s->key),
        'title' => $s->title ?: ($s->nav_label ?: Str::headline($s->key)),
        'eyebrow' => $s->key === 'home' ? null : $s->eyebrow,
    ])->values();
    if ($hasBlog || $hasNotes) {
        $writing = ['key' => 'writing', 'label' => 'Writing', 'title' => 'Writing', 'eyebrow' => 'Fresh from the notebook'];
        $at = $blocks->search(fn ($b) => $b['key'] === 'contact');
        $blocks = $at === false ? $blocks->push($writing) : $blocks->splice(0, $at)->push($writing)->concat($blocks)->values();
    }
    $nav = $blocks->map(fn ($b) => ['href' => '#'.$b['key'], 'emoji' => $emoji[$b['key']] ?? '✨', 'label' => $b['label'], 'key' => $b['key']])->all();
    $keys = $blocks->pluck('key');
    $greeting = $sections->firstWhere('key', 'home')?->eyebrow ?: "Hi there, I'm";

    $meta = [];
@endphp
@extends('portfolios.templates.picto._layout')

@section('head')
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Person',
      'name' => $profile->full_name,
      'jobTitle' => $profile->headline ?: null,
      'url' => route('home'),
      'image' => $profile->avatarSrc(),
      'email' => $profile->email ? 'mailto:'.$profile->email : null,
      'address' => $profile->location,
      'worksFor' => $currentJob ? ['@type' => 'Organization', 'name' => $currentJob->company, 'url' => $currentJob->company_url] : null,
      'alumniOf' => $education->map(fn ($e) => ['@type' => 'EducationalOrganization', 'name' => $e->institution])->all() ?: null,
      'sameAs' => $links->pluck('url')->all() ?: null,
      'knowsAbout' => $skillCategories->flatMap->skills->pluck('name')->all() ?: null,
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('body')
  {{-- Hero --}}
  <header class="pc-hero" aria-labelledby="hero-h">
    <div class="pc-hero-text">
      <p class="pc-hello"><span aria-hidden="true">👋</span> {{ $greeting }}</p>
      <h1 id="hero-h" class="pc-name">{{ $profile->full_name }}</h1>
      @if ($profile->headline)<p class="pc-headline"><span class="pc-squiggle">{{ $profile->headline }}</span></p>@endif
      @if ($profile->intro)<p class="pc-intro">{!! $md($profile->intro) !!}</p>@endif
      @if ($keys->contains('contact') || $keys->contains('projects') || $profile->resume)
        <p class="pc-ctas">
          @if ($keys->contains('contact'))<a class="pc-btn pc-btn-main" href="#contact"><span aria-hidden="true">💌</span> Say hello</a>@endif
          @if ($keys->contains('projects'))<a class="pc-btn" href="#projects"><span aria-hidden="true">🚀</span> See my work</a>@endif
          @if ($profile->resume)<a class="pc-btn" href="{{ route('resume') }}"><span aria-hidden="true">📄</span> Download CV</a>@endif
        </p>
      @endif
    </div>

    <div class="pc-hero-art">
      @if ($profile->avatarSrc())
        <img class="pc-avatar" src="{{ $profile->avatarSrc() }}" alt="Portrait of {{ $profile->full_name }}" width="280" height="280">
      @else
        <span class="pc-avatar pc-avatar-initials" aria-hidden="true">{{ collect(preg_split('/\s+/', trim($profile->full_name)))->filter()->map(fn ($n) => mb_substr($n, 0, 1))->take(2)->implode('') }}</span>
      @endif
      @if ($profile->location || $profile->open_to_work || $currentJob)
        <ul class="pc-stickers" aria-label="Quick facts">
          @if ($profile->open_to_work)<li class="c3"><span aria-hidden="true">🟢</span> Open to work</li>@endif
          @if ($currentJob)<li class="c4"><span aria-hidden="true">💼</span> Now at {{ $currentJob->company }}</li>@endif
          @if ($profile->location)<li class="c2"><span aria-hidden="true">📍</span> {{ $profile->location }}</li>@endif
        </ul>
      @endif
      @if ($deco)
        <div class="pc-floaties" aria-hidden="true"><span>✨</span><span>🚀</span><span>💡</span><span>🎈</span></div>
      @endif
    </div>
  </header>

  @foreach ($blocks as $b)
    @php($c = 'c'.($loop->index % 4 + 1))
    <section id="{{ $b['key'] }}" class="pc-section pc-sec-{{ $b['key'] }}" aria-labelledby="{{ $b['key'] }}-h">
      <div class="pc-head">
        <span class="pc-marker {{ $c }}" aria-hidden="true">{{ $emoji[$b['key']] ?? '✨' }}</span>
        <div>
          @if ($b['eyebrow'])<p class="pc-eyebrow">{{ $b['eyebrow'] }}</p>@endif
          <h2 id="{{ $b['key'] }}-h">{{ $b['title'] }}</h2>
        </div>
      </div>

      @switch($b['key'])
        @case('home')
          @if ($about)
            <div class="pc-card pc-bio">
              <p>{!! $md($about[0]) !!}</p>
              @if (count($about) > 1)
                <details class="pc-more">
                  <summary><span aria-hidden="true">🍪</span> A little more about me</summary>
                  @foreach (array_slice($about, 1) as $para)<p>{!! $md($para) !!}</p>@endforeach
                </details>
              @endif
            </div>
          @endif

          @if ($expertise->isNotEmpty())
            <h3 class="pc-sub">What I'm good at</h3>
            <ul class="pc-grid pc-focus">
              @foreach ($expertise as $area)
                <li class="pc-card pc-tint c{{ $loop->index % 4 + 1 }}">
                  <h4>{{ $area->title }}</h4>
                  @if ($area->description)<p>{!! $md($area->description) !!}</p>@endif
                </li>
              @endforeach
            </ul>
          @endif

          @if ($testimonials->isNotEmpty())
            <h3 class="pc-sub">Kind words</h3>
            <div class="pc-grid pc-quotes">
              @foreach ($testimonials as $t)
                <figure class="pc-card pc-quote">
                  <blockquote><p>{{ $t->quote }}</p></blockquote>
                  <figcaption><span aria-hidden="true">💬</span> {{ collect([$t->name, $t->position, $t->company])->filter()->implode(', ') }}</figcaption>
                </figure>
              @endforeach
            </div>
          @endif
          @break

        @case('experience')
          @if ($experiences->isNotEmpty())
            <ol class="pc-timeline">
              @foreach ($experiences as $job)
                <li class="pc-step c{{ $loop->index % 4 + 1 }}">
                  <span class="pc-dot" aria-hidden="true">{{ $job->is_current ? '🌱' : '💼' }}</span>
                  <div class="pc-card">
                    <p class="pc-when">{{ $job->period() }}</p>
                    <h3>{{ $job->role }} <span class="pc-at">at
                      @if ($job->company_url)<a href="{{ $job->company_url }}" target="_blank" rel="noopener">{{ $job->company }}<span class="sr-only"> (opens in a new tab)</span></a>@else{{ $job->company }}@endif</span>
                    </h3>
                    @if ($job->employment_type || $job->location)<p class="pc-meta">{{ collect([$job->employment_type ? Str::of($job->employment_type)->replace('_', '-')->ucfirst() : null, $job->location])->filter()->implode(' · ') }}</p>@endif
                    @if ($job->summary)<p>{!! $md($job->summary) !!}</p>@endif
                    @if ($job->highlights)
                      <ul class="pc-checks">@foreach ($job->highlights as $point)<li>{!! $md($point) !!}</li>@endforeach</ul>
                    @endif
                    @if ($job->technologies->isNotEmpty())
                      <ul class="pc-tags" aria-label="Technologies">@foreach ($job->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul>
                    @endif
                  </div>
                </li>
              @endforeach
            </ol>
          @endif
          @if ($milestones->isNotEmpty())
            <h3 class="pc-sub">Milestones</h3>
            <ol class="pc-grid pc-milestones">
              @foreach ($milestones as $m)
                <li class="pc-card pc-tint c{{ $loop->index % 4 + 1 }}">
                  <p class="pc-when"><span aria-hidden="true">🏁</span> {{ $m->date_label }}</p>
                  <h4>{{ $m->title }}</h4>
                  @if ($m->summary)<p>{!! $md($m->summary) !!}</p>@endif
                </li>
              @endforeach
            </ol>
          @endif
          @break

        @case('projects')
          @if ($projects->isNotEmpty())
            <ul class="pc-projects">
              @foreach ($projects as $p)
                @php($pc = 'c'.($loop->index % 4 + 1))
                <li class="pc-project pc-card {{ $pc }} @if ($p->is_featured) is-featured @endif">
                  @if ($p->thumbnail)
                    <img class="pc-shot" src="{{ $p->thumbnail->url() }}" alt="" loading="lazy" width="640" height="400">
                  @else
                    <div class="pc-shot pc-shot-fallback {{ $pc }}" aria-hidden="true"><span>@include('portfolios.templates.picto._emoji', ['for' => $p->category, 'fallback' => '🚀'])</span><b>{{ mb_substr($p->name, 0, 1) }}</b></div>
                  @endif
                  <div class="pc-project-body">
                    <p class="pc-badges">
                      <span class="pc-badge {{ $pc }}"><span aria-hidden="true">@include('portfolios.templates.picto._emoji', ['for' => $p->category, 'fallback' => '🧩'])</span> {{ $p->category ?: 'Project' }}</span>
                      @if ($p->is_featured)<span class="pc-badge c4"><span aria-hidden="true">⭐</span> Featured</span>@endif
                    </p>
                    <h3><a class="pc-stretch" href="{{ route('projects.show', ['project' => $p]) }}">{{ $p->name }}</a></h3>
                    @php($metaLine = collect([$p->period(), $p->experience?->company])->filter()->implode(' · '))
                    @if ($metaLine)<p class="pc-meta">{{ $metaLine }}</p>@endif
                    @if ($p->tagline)<p>{{ $p->tagline }}</p>@elseif ($p->description)<p>{{ Str::limit(strip_tags($md($p->description)), 180) }}</p>@endif
                    @if ($p->technologies->isNotEmpty())
                      <ul class="pc-tags" aria-label="Stack">@foreach ($p->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul>
                    @endif
                    @if ($p->live_url || $p->repo_url)
                      <p class="pc-links">
                        @if ($p->live_url)<a href="{{ $p->live_url }}" target="_blank" rel="noopener"><span aria-hidden="true">🌐</span> Live<span class="sr-only"> site for {{ $p->name }} (opens in a new tab)</span></a>@endif
                        @if ($p->repo_url)<a href="{{ $p->repo_url }}" target="_blank" rel="noopener"><span aria-hidden="true">💻</span> Code<span class="sr-only"> of {{ $p->name }} (opens in a new tab)</span></a>@endif
                      </p>
                    @endif
                  </div>
                </li>
              @endforeach
            </ul>
          @endif
          @if ($highlights->isNotEmpty())
            <h3 class="pc-sub">Engineering highlights</h3>
            <ul class="pc-grid pc-highlights">
              @foreach ($highlights as $h)
                <li class="pc-card pc-tint c{{ $loop->index % 4 + 1 }}">
                  <h4><span aria-hidden="true">💡</span> {{ $h->title }}</h4>
                  @if ($h->summary)<p>{!! $md($h->summary) !!}</p>@endif
                  @if ($h->project)<p class="pc-meta"><a href="{{ route('projects.show', ['project' => $h->project]) }}">{{ $h->project->name }}</a></p>@endif
                </li>
              @endforeach
            </ul>
          @endif
          @break

        @case('skills')
          <div class="pc-skillsets">
            @foreach ($skillCategories as $cat)
              @php($sc = 'c'.($loop->index % 4 + 1))
              <div class="pc-skillset pc-card {{ $sc }}">
                <h3><span class="pc-skillset-ico {{ $sc }}" aria-hidden="true">@include('portfolios.templates.picto._emoji', ['for' => $cat->name])</span> {{ $cat->name }}</h3>
                @if ($cat->description)<p class="pc-meta">{{ $cat->description }}</p>@endif
                <ul class="pc-tiles">
                  @foreach ($cat->skills as $skill)
                    <li class="pc-tile" @if ($skill->description) title="{{ $skill->description }}" @endif>
                      <span class="pc-tile-ico {{ $sc }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($skill->name, 0, 1)) }}</span>
                      <span class="pc-tile-label">{{ $skill->name }}</span>
                      @if ($skill->levelLabel())<span class="pc-tile-level">{{ $skill->levelLabel() }}</span>@endif
                    </li>
                  @endforeach
                </ul>
              </div>
            @endforeach
          </div>
          @break

        @case('education')
          <ul class="pc-grid pc-edu">
            @foreach ($education as $ed)
              <li class="pc-card pc-tint c{{ $loop->index % 4 + 1 }}">
                <span class="pc-sticker" aria-hidden="true">🎓</span>
                @if ($ed->period())<p class="pc-when">{{ $ed->period() }}</p>@endif
                <h3>{{ collect([$ed->degree, $ed->field])->filter()->implode(', ') ?: $ed->institution }}</h3>
                @if ($ed->degree || $ed->field)<p class="pc-meta">{{ collect([$ed->institution, $ed->location])->filter()->implode(' · ') }}</p>@endif
                @if ($ed->description)<p>{!! $md($ed->description) !!}</p>@endif
              </li>
            @endforeach
            @foreach ($certifications as $cert)
              <li class="pc-card pc-tint c{{ ($loop->index + $education->count()) % 4 + 1 }}">
                <span class="pc-sticker" aria-hidden="true">📜</span>
                <p class="pc-when">Certificate @if ($cert->issued_on)· {{ $cert->issued_on->format('M Y') }}@endif</p>
                <h3>@if ($cert->credential_url)<a href="{{ $cert->credential_url }}" target="_blank" rel="noopener">{{ $cert->name }}<span class="sr-only"> (opens in a new tab)</span></a>@else{{ $cert->name }}@endif</h3>
                @if ($cert->issuer)<p class="pc-meta">{{ $cert->issuer }}</p>@endif
              </li>
            @endforeach
          </ul>
          @break

        @case('writing')
          @if ($hasBlog)
            <ul class="pc-grid pc-posts">
              @foreach ($posts->take(4) as $post)
                <li class="pc-card pc-post c{{ $loop->index % 4 + 1 }}">
                  <p class="pc-when"><span aria-hidden="true">📝</span> <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('j M Y') }}</time></p>
                  <h3><a class="pc-stretch" href="{{ $post->url() }}">{{ $post->title }}</a></h3>
                  @if ($post->excerpt)<p>{{ Str::limit($post->excerpt, 140) }}</p>@endif
                </li>
              @endforeach
            </ul>
          @endif
          <p class="pc-ctas">
            @if ($hasBlog)<a class="pc-btn" href="{{ route('blog.index') }}"><span aria-hidden="true">📚</span> All articles</a>@endif
            @if ($hasNotes)<a class="pc-btn" href="{{ route('notes.index') }}"><span aria-hidden="true">🗒️</span> Notes ({{ $notes->count() }})</a>@endif
          </p>
          @break

        @case('contact')
          <div class="pc-contact">
            <div class="pc-contact-cta pc-card c1">
              <p class="pc-contact-big"><span aria-hidden="true">💌</span> {{ $profile->open_to_work ? "I'm open to new roles and collaborations — let's talk!" : 'Got an idea or a question? Drop me a line.' }}</p>
              <ul class="pc-reach">
                @if ($profile->email)<li><a href="mailto:{{ $profile->email }}"><span aria-hidden="true">✉️</span> {{ $profile->email }}</a></li>@endif
                @if ($phone)<li><a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}"><span aria-hidden="true">📞</span> {{ $phone }}</a></li>@endif
                @foreach ($links as $link)
                  <li><a href="{{ $link->url }}" rel="me noopener" target="_blank"><span aria-hidden="true">@include('portfolios.templates.picto._emoji', ['for' => $link->platform, 'fallback' => '🔗'])</span> {{ $link->label ?: Str::headline($link->platform) }}<span class="sr-only"> (opens in a new tab)</span></a></li>
                @endforeach
                @if ($profile->resume)<li><a href="{{ route('resume') }}"><span aria-hidden="true">📄</span> Résumé (PDF)</a></li>@endif
              </ul>
            </div>

            @if ($profile->contact_form_enabled)
              <div class="pc-card pc-form-card">
                @if (session('contact_sent'))
                  <p class="pc-notice" role="status"><span aria-hidden="true">🎉</span> Thanks — your message is on its way. I'll reply soon.</p>
                @endif
                <form class="pc-form" method="post" action="{{ route('contact.store') }}" novalidate>
                  @csrf
                  @if ($errors->any())
                    <p class="pc-error" role="alert">Please fix the highlighted fields below.</p>
                  @endif
                  <div class="pc-hp" aria-hidden="true">
                    <label for="c-website">Leave this field empty</label>
                    <input id="c-website" type="text" name="website" tabindex="-1" autocomplete="off">
                  </div>
                  @foreach ([
                      ['name', 'Your name', 'text', 'name', true],
                      ['email', 'Your email', 'email', 'email', true],
                      ['subject', 'Subject (optional)', 'text', 'off', false],
                  ] as [$field, $label, $type, $auto, $required])
                    <div class="pc-field">
                      <label for="c-{{ $field }}">{{ $label }}</label>
                      <input id="c-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" autocomplete="{{ $auto }}" @if ($required) required @endif
                        @error($field) aria-invalid="true" aria-describedby="c-{{ $field }}-err" @enderror>
                      @error($field)<p id="c-{{ $field }}-err" class="pc-field-error">{{ $message }}</p>@enderror
                    </div>
                  @endforeach
                  <div class="pc-field">
                    <label for="c-message">Message</label>
                    <textarea id="c-message" name="message" rows="5" required @error('message') aria-invalid="true" aria-describedby="c-message-err" @enderror>{{ old('message') }}</textarea>
                    @error('message')<p id="c-message-err" class="pc-field-error">{{ $message }}</p>@enderror
                  </div>
                  <button class="pc-btn pc-btn-main" type="submit"><span aria-hidden="true">🚀</span> Send message</button>
                </form>
              </div>
            @elseif (session('contact_sent'))
              <p class="pc-notice" role="status">Thanks — your message is on its way.</p>
            @endif
          </div>
          @break
        @default
          <div class="pc-card">@include('portfolios.partials.text-section', ['key' => $b['key']])</div>
      @endswitch
    </section>
  @endforeach
@endsection

@section('footer-links')
  @if ($links->isNotEmpty() || $profile->email)
    <ul class="pc-foot-links" aria-label="Elsewhere">
      @foreach ($links as $link)
        <li><a href="{{ $link->url }}" rel="me noopener" target="_blank"><span aria-hidden="true">@include('portfolios.templates.picto._emoji', ['for' => $link->platform, 'fallback' => '🔗'])</span> {{ $link->label ?: Str::headline($link->platform) }}<span class="sr-only"> (opens in a new tab)</span></a></li>
      @endforeach
      @if ($profile->email)<li><a href="mailto:{{ $profile->email }}"><span aria-hidden="true">✉️</span> Email</a></li>@endif
    </ul>
  @endif
@endsection
