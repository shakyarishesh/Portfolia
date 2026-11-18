@php
    use Illuminate\Support\Str;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $page = 'home';
    $footerLinks = $links;

    // Header links follow the admin's section order/labels. "home" points at the hero; "Writing" slots in before Contact.
    $nav = $sections->map(fn ($s) => [
        'key' => $s->key,
        'anchor' => $s->key,
        'label' => $s->nav_label ?: Str::headline($s->key),
        'title' => $s->title ?: ($s->nav_label ?: Str::headline($s->key)),
        'eyebrow' => $s->eyebrow,
    ])->values();
    if ($hasBlog || $hasNotes) {
        $writing = ['key' => 'writing', 'anchor' => 'writing', 'label' => 'Writing', 'title' => 'Writing', 'eyebrow' => 'Latest'];
        $at = $nav->search(fn ($n) => $n['key'] === 'contact');
        $nav = $at === false ? $nav->push($writing) : $nav->splice(0, $at)->push($writing)->concat($nav)->values();
    }
    $hasNav = fn (string $key) => $nav->contains('key', $key);
    $hello = $sections->firstWhere('key', 'home')?->eyebrow; // e.g. "Hello, I'm" — greets in the hero, not above About.

    // The single gradient-accented phrase: the headline's last word (last two once the headline has four or more).
    $words = preg_split('/\s+/u', trim((string) $profile->headline), -1, PREG_SPLIT_NO_EMPTY);
    $accent = $words ? implode(' ', array_splice($words, count($words) >= 4 ? -2 : -1)) : null;
    $lead = implode(' ', $words);

    // About stat cards, counted from published content only.
    $stats = array_filter([
        'Projects' => $projects->count(),
        'Skills' => $skillCategories->sum(fn ($c) => $c->skills->count()),
        'Roles' => $experiences->count(),
    ]);
    $about = $profile->aboutParagraphs();
    $newTab = '<span class="sr-only"> (opens in a new tab)</span>';
@endphp
@extends('portfolios.templates.gradient._layout')

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
<main id="content" tabindex="-1">
  {{-- Hero: always present, it holds the page's only h1. --}}
  <section id="home" class="gd-hero" aria-labelledby="hero-name">
    @if ($settings['shapes'] ?? true)
      <div class="gd-shapes" aria-hidden="true"><span class="gd-shape gd-shape-a"></span><span class="gd-shape gd-shape-b"></span><span class="gd-shape gd-shape-c"></span></div>
    @endif
    <div class="gd-wrap gd-hero-grid">
      <div class="gd-hero-copy">
        @if ($profile->open_to_work)<p class="gd-pill"><span class="gd-dot" aria-hidden="true"></span>Open to new opportunities</p>@endif
        <h1 id="hero-name" class="gd-name">@if ($hello)<span class="gd-hello">{{ $hello }}</span> @endif{{ $profile->full_name }}</h1>
        @if ($accent)
          <p class="gd-headline">@if ($lead){{ $lead }} @endif<span class="gd-accent-phrase">{{ $accent }}</span></p>
        @endif
        @if ($profile->intro)<p class="gd-intro">{!! $md($profile->intro) !!}</p>@endif
        <div class="gd-actions">
          @if ($hasNav('contact'))<a class="gd-btn" href="#contact">Get in touch</a>@endif
          @if ($hasNav('projects'))<a class="gd-btn gd-btn-ghost" href="#projects">View projects</a>@endif
          @if ($profile->resume)<a class="gd-btn gd-btn-ghost" href="{{ route('resume') }}">Download CV</a>@endif
        </div>
        @if ($profile->location || $links->isNotEmpty())
          <ul class="gd-hero-meta" aria-label="Location and profiles">
            @if ($profile->location)<li>{{ $profile->location }}</li>@endif
            @foreach ($links as $link)
              <li><a href="{{ $link->url }}" rel="me noopener" target="_blank">{{ $link->label ?: Str::headline($link->platform) }}{!! $newTab !!}</a></li>
            @endforeach
          </ul>
        @endif
      </div>
      @if ($profile->avatarSrc())
        <div class="gd-portrait"><img src="{{ $profile->avatarSrc() }}" alt="Portrait of {{ $profile->full_name }}" width="320" height="320" fetchpriority="high"></div>
      @endif
    </div>
  </section>

  @foreach ($nav as $item)
    @php($head = $item['key'].'-h')
    @switch($item['key'])
      @case('home')
        @if ($about || $expertise->isNotEmpty() || $stats)
          <section id="about" class="gd-section gd-about" aria-labelledby="{{ $head }}">
            <div class="gd-wrap">
              <p class="gd-eyebrow">Profile</p>
              <h2 id="{{ $head }}" class="gd-h2">{{ $item['title'] }}</h2>
              <div class="gd-about-grid">
                <div class="gd-prose">
                  @foreach ($about as $para)<p>{!! $md($para) !!}</p>@endforeach
                </div>
                @if ($stats)
                  <ul class="gd-stats" aria-label="At a glance">
                    @foreach ($stats as $label => $count)
                      <li class="gd-stat"><span class="gd-stat-num">{{ $count }}</span> <span class="gd-stat-label">{{ $label }}</span></li>
                    @endforeach
                  </ul>
                @endif
              </div>
              @if ($expertise->isNotEmpty())
                <h3 class="gd-h3 gd-sub">Focus areas</h3>
                <ul class="gd-cards gd-cards-3">
                  @foreach ($expertise as $area)
                    <li class="gd-card gd-edge-top reveal">
                      <h4 class="gd-h4">{{ $area->title }}</h4>
                      @if ($area->description)<p class="gd-muted">{!! $md($area->description) !!}</p>@endif
                    </li>
                  @endforeach
                </ul>
              @endif
            </div>
          </section>
        @endif
        @if ($testimonials->isNotEmpty())
          <section id="testimonials" class="gd-section" aria-labelledby="testimonials-h">
            <div class="gd-wrap">
              <p class="gd-eyebrow">Testimonials</p>
              <h2 id="testimonials-h" class="gd-h2">Kind words</h2>
              <div class="gd-cards gd-cards-2">
                @foreach ($testimonials as $t)
                  <figure class="gd-card gd-quote reveal">
                    <blockquote><p>{{ $t->quote }}</p></blockquote>
                    <figcaption><b>{{ $t->name }}</b>@if ($t->position || $t->company)<span class="gd-muted"> — {{ collect([$t->position, $t->company])->filter()->implode(', ') }}</span>@endif</figcaption>
                  </figure>
                @endforeach
              </div>
            </div>
          </section>
        @endif
        @break

      @case('experience')
        <section id="experience" class="gd-section" aria-labelledby="{{ $head }}">
          <div class="gd-wrap">
            @if ($item['eyebrow'])<p class="gd-eyebrow">{{ $item['eyebrow'] }}</p>@endif
            <h2 id="{{ $head }}" class="gd-h2">{{ $item['title'] }}</h2>
            @if ($experiences->isNotEmpty())
              <ol class="gd-timeline">
                @foreach ($experiences as $job)
                  <li class="gd-tl-item reveal">
                    <p class="gd-tl-when">{{ $job->period() }}@if ($job->is_current) <span class="gd-badge">Current</span>@endif</p>
                    <h3 class="gd-h3">{{ $job->role }}</h3>
                    <p class="gd-tl-org">
                      @if ($job->company_url)<a href="{{ $job->company_url }}" target="_blank" rel="noopener">{{ $job->company }}{!! $newTab !!}</a>@else{{ $job->company }}@endif
                      @php($jobMeta = collect([$job->employment_type ? Str::of($job->employment_type)->replace('_', '-')->ucfirst() : null, $job->location])->filter()->implode(' · '))
                      @if ($jobMeta)<span class="gd-muted"> · {{ $jobMeta }}</span>@endif
                    </p>
                    @if ($job->summary)<p>{!! $md($job->summary) !!}</p>@endif
                    @if ($job->highlights)
                      <ul class="gd-points">@foreach ($job->highlights as $point)<li>{!! $md($point) !!}</li>@endforeach</ul>
                    @endif
                    @if ($job->technologies->isNotEmpty())
                      <ul class="gd-chips" aria-label="Technologies">@foreach ($job->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul>
                    @endif
                  </li>
                @endforeach
              </ol>
            @endif
            @if ($milestones->isNotEmpty())
              <h3 class="gd-h3 gd-sub">Milestones</h3>
              <ol class="gd-milestones">
                @foreach ($milestones as $m)
                  <li class="gd-card reveal">
                    @if ($m->date_label)<p class="gd-badge">{{ $m->date_label }}</p>@endif
                    <h4 class="gd-h4">{{ $m->title }}</h4>
                    @if ($m->summary)<p class="gd-muted">{!! $md($m->summary) !!}</p>@endif
                  </li>
                @endforeach
              </ol>
            @endif
          </div>
        </section>
        @break

      @case('projects')
        <section id="projects" class="gd-section" aria-labelledby="{{ $head }}">
          <div class="gd-wrap">
            @if ($item['eyebrow'])<p class="gd-eyebrow">{{ $item['eyebrow'] }}</p>@endif
            <h2 id="{{ $head }}" class="gd-h2">{{ $item['title'] }}</h2>
            @if ($projects->isNotEmpty())
              <div class="gd-projects">
                @foreach ($projects as $p)
                  <article class="gd-project {{ $p->is_featured ? 'is-featured' : '' }} {{ $p->thumbnail ? 'has-media' : 'no-media' }} reveal" aria-labelledby="p-{{ $p->slug }}">
                    @if ($p->thumbnail)
                      <div class="gd-project-media"><img src="{{ $p->thumbnail->url() }}" alt="" loading="lazy" width="640" height="360"></div>
                    @endif
                    <div class="gd-project-body">
                      @php($projMeta = collect([$p->category, $p->period()])->filter()->implode(' · '))
                      @if ($p->is_featured || $projMeta)
                        <p class="gd-project-meta">@if ($p->is_featured)<span class="gd-badge">Featured</span> @endif{{ $projMeta }}</p>
                      @endif
                      <h3 class="gd-h3" id="p-{{ $p->slug }}"><a class="gd-title-link" href="{{ route('projects.show', ['project' => $p]) }}">{{ $p->name }}</a></h3>
                      @if ($p->tagline)<p>{{ $p->tagline }}</p>@elseif ($p->description)<p>{{ Str::limit(strip_tags($md($p->description)), 200) }}</p>@endif
                      @if ($p->technologies->isNotEmpty())
                        <ul class="gd-chips" aria-label="Stack">@foreach ($p->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul>
                      @endif
                      <p class="gd-links">
                        <a href="{{ route('projects.show', ['project' => $p]) }}">Case study<span class="sr-only">: {{ $p->name }}</span> →</a>
                        @if ($p->live_url)<a href="{{ $p->live_url }}" target="_blank" rel="noopener">Live demo<span class="sr-only"> of {{ $p->name }} (opens in a new tab)</span> ↗</a>@endif
                        @if ($p->repo_url)<a href="{{ $p->repo_url }}" target="_blank" rel="noopener">Source<span class="sr-only"> of {{ $p->name }} (opens in a new tab)</span> ↗</a>@endif
                      </p>
                    </div>
                  </article>
                @endforeach
              </div>
            @endif
            @if ($highlights->isNotEmpty())
              <h3 class="gd-h3 gd-sub">Engineering highlights</h3>
              <ul class="gd-cards gd-cards-3">
                @foreach ($highlights as $h)
                  <li class="gd-card gd-edge-top reveal">
                    @if ($h->label)<p class="gd-badge">{{ $h->label }}</p>@endif
                    <h4 class="gd-h4">{{ $h->title }}</h4>
                    @if ($h->summary)<p class="gd-muted">{!! $md($h->summary) !!}</p>@endif
                    @if ($h->project)<p class="gd-small"><a href="{{ route('projects.show', ['project' => $h->project]) }}">{{ $h->project->name }}</a></p>@endif
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </section>
        @break

      @case('skills')
        <section id="skills" class="gd-section" aria-labelledby="{{ $head }}">
          <div class="gd-wrap">
            @if ($item['eyebrow'])<p class="gd-eyebrow">{{ $item['eyebrow'] }}</p>@endif
            <h2 id="{{ $head }}" class="gd-h2">{{ $item['title'] }}</h2>
            <div class="gd-cards gd-cards-3">
              @foreach ($skillCategories as $cat)
                <div class="gd-card gd-skillset gd-edge-left reveal">
                  <h3 class="gd-h4">{{ $cat->name }}</h3>
                  <ul class="gd-chips">
                    @foreach ($cat->skills as $skill)
                      <li @if ($skill->description) title="{{ $skill->description }}" @endif>{{ $skill->name }}@if ($skill->levelLabel())<span class="gd-level"> · {{ $skill->levelLabel() }}</span>@endif</li>
                    @endforeach
                  </ul>
                </div>
              @endforeach
            </div>
          </div>
        </section>
        @break

      @case('education')
        {{-- Education and certificates are separate first-class sections; whichever renders first carries the #education anchor. --}}
        @if ($education->isNotEmpty())
          <section id="education" class="gd-section gd-education" aria-labelledby="{{ $head }}">
            <div class="gd-wrap">
              @if ($item['eyebrow'])<p class="gd-eyebrow">{{ $item['eyebrow'] }}</p>@endif
              <h2 id="{{ $head }}" class="gd-h2">{{ $item['title'] }}</h2>
              <ol class="gd-cards gd-cards-2">
                @foreach ($education as $ed)
                  <li class="gd-card gd-degree reveal">
                    @if ($ed->period())<p class="gd-badge">{{ $ed->period() }}</p>@endif
                    <h3 class="gd-h3">{{ collect([$ed->degree, $ed->field])->filter()->implode(', ') ?: $ed->institution }}</h3>
                    @if ($ed->degree || $ed->field)<p class="gd-degree-school">{{ $ed->institution }}</p>@endif
                    @if ($ed->location)<p class="gd-muted gd-small">{{ $ed->location }}</p>@endif
                    @if ($ed->description)<p>{!! $md($ed->description) !!}</p>@endif
                  </li>
                @endforeach
              </ol>
            </div>
          </section>
        @endif
        @if ($certifications->isNotEmpty())
          <section id="{{ $education->isNotEmpty() ? 'certificates' : 'education' }}" class="gd-section gd-certificates" aria-labelledby="certificates-h">
            <div class="gd-wrap">
              <p class="gd-eyebrow">Credentials</p>
              <h2 id="certificates-h" class="gd-h2">Certificates</h2>
              <ul class="gd-cert-list">
                @foreach ($certifications as $cert)
                  <li class="gd-cert reveal">
                    <span class="gd-cert-mark" aria-hidden="true"></span>
                    <div>
                      <h3 class="gd-h4">@if ($cert->credential_url)<a href="{{ $cert->credential_url }}" target="_blank" rel="noopener">{{ $cert->name }}{!! $newTab !!}</a>@else{{ $cert->name }}@endif</h3>
                      @php($certMeta = collect([$cert->issuer, $cert->issued_on?->format('M Y')])->filter()->implode(' · '))
                      @if ($certMeta)<p class="gd-muted gd-small">{{ $certMeta }}</p>@endif
                    </div>
                  </li>
                @endforeach
              </ul>
            </div>
          </section>
        @endif
        @break

      @case('writing')
        <section id="writing" class="gd-section" aria-labelledby="{{ $head }}">
          <div class="gd-wrap">
            <p class="gd-eyebrow">{{ $item['eyebrow'] }}</p>
            <h2 id="{{ $head }}" class="gd-h2">{{ $item['title'] }}</h2>
            @if ($hasBlog)
              <ul class="gd-cards gd-cards-3">
                @foreach ($posts->take(3) as $post)
                  <li class="gd-card gd-edge-top reveal">
                    @if ($post->published_at)<p class="gd-small gd-muted"><time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('j M Y') }}</time></p>@endif
                    <h3 class="gd-h4"><a class="gd-title-link" href="{{ $post->url() }}">{{ $post->title }}</a></h3>
                    @if ($post->excerpt)<p class="gd-muted">{{ Str::limit($post->excerpt, 150) }}</p>@endif
                  </li>
                @endforeach
              </ul>
            @endif
            <p class="gd-links">
              @if ($hasBlog)<a href="{{ route('blog.index') }}">All articles →</a>@endif
              @if ($hasNotes)<a href="{{ route('notes.index') }}">Notes ({{ $notes->count() }}) →</a>@endif
            </p>
          </div>
        </section>
        @break

      @case('contact')
        <section id="contact" class="gd-section gd-contact" aria-labelledby="{{ $head }}">
          <div class="gd-wrap">
            <div class="gd-contact-panel {{ $profile->contact_form_enabled ? 'has-form' : '' }}">
              <div class="gd-contact-copy">
                @if ($item['eyebrow'])<p class="gd-eyebrow">{{ $item['eyebrow'] }}</p>@endif
                <h2 id="{{ $head }}" class="gd-h2">{{ $item['title'] }}</h2>
                <p class="gd-lede">
                  @if ($profile->open_to_work)I'm open to new roles and collaborations. @endif
                  @if ($profile->email)The quickest way to reach me is by email.@elseif ($profile->contact_form_enabled)Send me a message using the form.@else Find me through the links below.@endif
                </p>
                @if ($profile->email)<p><a class="gd-btn" href="mailto:{{ $profile->email }}">{{ $profile->email }}</a></p>@endif
                <ul class="gd-contact-list">
                  @if ($profile->show_phone && $profile->phone)<li><span class="gd-muted">Phone</span> <a href="tel:{{ preg_replace('/[^\d+]/', '', $profile->phone) }}">{{ $profile->phone }}</a></li>@endif
                  @if ($profile->location)<li><span class="gd-muted">Based in</span> {{ $profile->location }}</li>@endif
                  @foreach ($links as $link)
                    <li><span class="gd-muted">{{ $link->label ?: Str::headline($link->platform) }}</span> <a href="{{ $link->url }}" rel="me noopener" target="_blank">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($link->url, '/')) }}{!! $newTab !!}</a></li>
                  @endforeach
                  @if ($profile->resume)<li><span class="gd-muted">CV</span> <a href="{{ route('resume') }}">Download (PDF)</a></li>@endif
                </ul>
              </div>

              @if ($profile->contact_form_enabled)
                <form class="gd-form" method="post" action="{{ route('contact.store') }}" novalidate>
                  @csrf
                  @if (session('contact_sent'))
                    <p class="gd-notice" role="status">Thanks — your message is on its way. I'll reply soon.</p>
                  @endif
                  @if ($errors->any())
                    <p class="gd-error" role="alert">Please fix the highlighted fields below.</p>
                  @endif
                  <div class="gd-hp" aria-hidden="true">
                    <label for="c-website">Leave this field empty</label>
                    <input id="c-website" type="text" name="website" tabindex="-1" autocomplete="off">
                  </div>
                  <div class="gd-form-row">
                    @foreach ([['name', 'Name', 'text', 'name'], ['email', 'Email', 'email', 'email']] as [$field, $label, $type, $auto])
                      <div class="gd-field">
                        <label for="c-{{ $field }}">{{ $label }}</label>
                        <input id="c-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" autocomplete="{{ $auto }}" required
                          @error($field) aria-invalid="true" aria-describedby="c-{{ $field }}-err" @enderror>
                        @error($field)<p id="c-{{ $field }}-err" class="gd-field-error">{{ $message }}</p>@enderror
                      </div>
                    @endforeach
                  </div>
                  <div class="gd-field">
                    <label for="c-subject">Subject (optional)</label>
                    <input id="c-subject" type="text" name="subject" value="{{ old('subject') }}" autocomplete="off" @error('subject') aria-invalid="true" aria-describedby="c-subject-err" @enderror>
                    @error('subject')<p id="c-subject-err" class="gd-field-error">{{ $message }}</p>@enderror
                  </div>
                  <div class="gd-field">
                    <label for="c-message">Message</label>
                    <textarea id="c-message" name="message" rows="5" required @error('message') aria-invalid="true" aria-describedby="c-message-err" @enderror>{{ old('message') }}</textarea>
                    @error('message')<p id="c-message-err" class="gd-field-error">{{ $message }}</p>@enderror
                  </div>
                  <button class="gd-btn" type="submit">Send message</button>
                </form>
              @elseif (session('contact_sent'))
                <p class="gd-notice" role="status">Thanks — your message is on its way. I'll reply soon.</p>
              @endif
            </div>
          </div>
        </section>
        @break
    @endswitch
  @endforeach
</main>
@endsection
