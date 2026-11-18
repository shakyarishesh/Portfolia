@php
    use Illuminate\Support\Str;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $icon = fn (string $name) => '<svg class="pro-i" aria-hidden="true" focusable="false"><use href="#pro-i-'.e($name).'"/></svg>';
    $socialIcon = fn ($link) => ['github' => 'code', 'gitlab' => 'code', 'linkedin' => 'briefcase'][$link->platform] ?? 'link';
    $page = 'home';

    // Stats are computed from real data only; a zero stat is dropped.
    $firstStart = $experiences->pluck('start_date')->filter()->min();
    $years = $firstStart ? (int) floor(abs($firstStart->diffInYears(now()))) : 0;
    $skillCount = $skillCategories->flatMap->skills->count();
    $stats = ($settings['show_stats'] ?? true) ? array_filter([
        'Years of experience' => $years ? $years.'+' : null,
        'Projects shipped' => $projects->count() ?: null,
        'Skills & tools' => $skillCount ?: null,
    ]) : [];
    $paragraphs = $profile->aboutParagraphs();
    $aboutHasContent = $paragraphs || $stats || $expertise->isNotEmpty();
    $contactHasContent = $profile->contact_form_enabled || $profile->email || ($profile->show_phone && $profile->phone) || $links->isNotEmpty() || session('contact_sent');

    // Newest first: the current role leads, then by start date.
    $timeline = $experiences->sortByDesc(fn ($e) => [$e->is_current ? 1 : 0, $e->start_date?->getTimestamp() ?? 0])->values();
    // Featured projects first (stable sort keeps the admin order within each group).
    $cards = $projects->sortByDesc(fn ($p) => $p->is_featured ? 1 : 0)->values();

    // Admin order/labels; "home" is the About block (dropped when empty); Writing slots in before Contact.
    $nav = $sections
        ->reject(fn ($s) => ($s->key === 'home' && ! $aboutHasContent) || ($s->key === 'contact' && ! $contactHasContent))
        ->map(fn ($s) => [
            'key' => $s->key === 'home' ? 'about' : $s->key,
            'section' => $s->key,
            'label' => $s->nav_label ?: Str::headline($s->key),
            'title' => $s->title ?: ($s->nav_label ?: Str::headline($s->key)),
            'eyebrow' => $s->eyebrow,
        ])->values();
    if ($hasBlog || $hasNotes) {
        $writing = ['key' => 'writing', 'section' => 'writing', 'label' => 'Writing', 'title' => 'Latest writing', 'eyebrow' => 'Writing'];
        $at = $nav->search(fn ($n) => $n['key'] === 'contact');
        $nav = $at === false ? $nav->push($writing) : $nav->splice(0, $at)->push($writing)->concat($nav)->values();
    }
    $has = fn (string $key) => $nav->contains('key', $key);
    $navItems = $nav->reject(fn ($n) => $n['key'] === 'contact')->map(fn ($n) => ['#'.$n['key'], $n['label']])->values()->all();
    // Testimonials follow About, or close the page when About is absent.
    $quotesAfter = $has('about') ? 'about' : $nav->last()['key'] ?? null;
    $contactHref = $has('contact') ? '#contact' : ($profile->email ? 'mailto:'.$profile->email : null);
@endphp
@extends('portfolios.templates.professional.layout')

@section('head')
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Person',
      'name' => $profile->full_name,
      'jobTitle' => $profile->headline ?: null,
      'description' => $profile->intro ? strip_tags($md($profile->intro)) : null,
      'url' => route('home'),
      'image' => $profile->avatarSrc(),
      'email' => $profile->email ? 'mailto:'.$profile->email : null,
      'address' => $profile->location,
      'worksFor' => $currentJob ? array_filter(['@type' => 'Organization', 'name' => $currentJob->company, 'url' => $currentJob->company_url]) : null,
      'alumniOf' => $education->map(fn ($e) => ['@type' => 'EducationalOrganization', 'name' => $e->institution])->all() ?: null,
      'sameAs' => $links->pluck('url')->all() ?: null,
      'knowsAbout' => $skillCategories->flatMap->skills->pluck('name')->all() ?: null,
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('body')
  {{-- Hero --}}
  <section class="pro-hero" aria-labelledby="pro-name">
    <div class="pro-wrap pro-hero-grid @unless ($profile->avatarSrc()) no-photo @endunless">
      <div class="pro-hero-text">
        @if ($profile->open_to_work)
          <p class="pro-status"><span class="pro-dot" aria-hidden="true"></span>Open to work</p>
        @endif
        <h1 id="pro-name" class="pro-name">{{ $profile->full_name }}</h1>
        @if ($profile->headline)<p class="pro-role">{{ $profile->headline }}</p>@endif
        @if ($profile->intro)<p class="pro-intro">{!! $md($profile->intro) !!}</p>@endif

        @if ($currentJob || $profile->location)
          <ul class="pro-facts">
            @if ($currentJob)<li>{!! $icon('briefcase') !!}<span>{{ $currentJob->role }} at {{ $currentJob->company }}</span></li>@endif
            @if ($profile->location)<li>{!! $icon('pin') !!}<span>{{ $profile->location }}</span></li>@endif
          </ul>
        @endif

        <p class="pro-ctas">
          @if ($contactHref)<a class="pro-btn pro-btn-primary" href="{{ $contactHref }}">{!! $icon('mail') !!}Contact Me</a>@endif
          @if ($has('projects'))<a class="pro-btn pro-btn-ghost" href="#projects">View Projects{!! $icon('arrow') !!}</a>@endif
          @if ($profile->resume)<a class="pro-btn pro-btn-link" href="{{ route('resume') }}">{!! $icon('download') !!}Download CV<span class="pro-sr"> (PDF)</span></a>@endif
        </p>
      </div>
      @if ($profile->avatarSrc())
        <figure class="pro-portrait">
          <img src="{{ $profile->avatarSrc() }}" alt="Portrait of {{ $profile->full_name }}" width="320" height="320" fetchpriority="high">
        </figure>
      @endif
    </div>
  </section>

  @foreach ($nav as $item)
    <section id="{{ $item['key'] }}" class="pro-section pro-section-{{ $item['key'] }}" aria-labelledby="{{ $item['key'] }}-h">
      <div class="pro-wrap">
        <header class="pro-section-head">
          @if ($item['eyebrow'])<p class="pro-eyebrow">{{ $item['eyebrow'] }}</p>@endif
          <h2 id="{{ $item['key'] }}-h" class="pro-h2">{{ $item['title'] }}</h2>
        </header>

        @switch($item['section'])
          @case('home')
            <div class="pro-about @if ($stats || $expertise->isNotEmpty()) has-aside @endif">
              @if ($paragraphs)
                <div class="pro-prose">
                  @foreach ($paragraphs as $para)<p>{!! $md($para) !!}</p>@endforeach
                </div>
              @endif
              @if ($stats || $expertise->isNotEmpty())
                <aside class="pro-about-aside" aria-label="At a glance">
                  @if ($stats)
                    <dl class="pro-stats">
                      @foreach ($stats as $label => $value)
                        <div class="pro-stat pro-reveal"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                      @endforeach
                    </dl>
                  @endif
                  @if ($expertise->isNotEmpty())
                    <h3 class="pro-h4">Focus areas</h3>
                    <ul class="pro-focus">
                      @foreach ($expertise as $area)
                        <li>{!! $icon('check') !!}<div><strong>{{ $area->title }}</strong>@if ($area->description)<span>{!! $md($area->description) !!}</span>@endif</div></li>
                      @endforeach
                    </ul>
                  @endif
                </aside>
              @endif
            </div>
            @break

          @case('skills')
            <div class="pro-grid pro-grid-3">
              @foreach ($skillCategories as $cat)
                <article class="pro-card pro-skill pro-reveal">
                  <h3 class="pro-card-title"><span class="pro-badge-icon">{!! $icon('layers') !!}</span>{{ $cat->name }}</h3>
                  <ul class="pro-skill-list">
                    @foreach ($cat->skills as $skill)
                      <li @if ($skill->description) title="{{ $skill->description }}" @endif><span>{{ $skill->name }}</span>@if ($skill->levelLabel())<span class="pro-level">{{ $skill->levelLabel() }}</span>@endif</li>
                    @endforeach
                  </ul>
                </article>
              @endforeach
            </div>
            @break

          @case('experience')
            @if ($timeline->isNotEmpty())
              <ol class="pro-timeline">
                @foreach ($timeline as $job)
                  <li class="pro-tl-item @if ($job->is_current) is-current @endif pro-reveal">
                    <article class="pro-card pro-job">
                      <header class="pro-job-head">
                        <div>
                          <h3 class="pro-card-title">{{ $job->role }}</h3>
                          <p class="pro-org">
                            @if ($job->company_url)<a href="{{ $job->company_url }}" target="_blank" rel="noopener">{{ $job->company }}<span class="pro-sr"> (opens in a new tab)</span></a>@else{{ $job->company }}@endif
                          </p>
                        </div>
                        @if ($job->is_current)<span class="pro-pill">Current</span>@endif
                      </header>
                      <ul class="pro-meta">
                        @if ($job->period())<li>{!! $icon('calendar') !!}{{ $job->period() }}</li>@endif
                        @if ($job->location)<li>{!! $icon('pin') !!}{{ $job->location }}</li>@endif
                        @if ($job->employment_type)<li>{!! $icon('briefcase') !!}{{ \App\Models\Experience::TYPES[$job->employment_type] ?? Str::headline($job->employment_type) }}</li>@endif
                      </ul>
                      @if ($job->summary)<p>{!! $md($job->summary) !!}</p>@endif
                      @if ($job->highlights)
                        <ul class="pro-checks">@foreach ($job->highlights as $point)<li>{!! $icon('check') !!}<span>{!! $md($point) !!}</span></li>@endforeach</ul>
                      @endif
                      @if ($job->technologies->isNotEmpty())
                        <ul class="pro-tags" aria-label="Technologies">@foreach ($job->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul>
                      @endif
                    </article>
                  </li>
                @endforeach
              </ol>
            @endif
            @if ($milestones->isNotEmpty())
              <h3 class="pro-h3">Milestones</h3>
              <ul class="pro-grid pro-grid-3 pro-plain">
                @foreach ($milestones as $m)
                  <li class="pro-card pro-mini pro-reveal">
                    @if ($m->date_label)<p class="pro-kicker">{{ $m->date_label }}</p>@endif
                    <p class="pro-strong">{{ $m->title }}</p>
                    @if ($m->summary)<p class="pro-muted">{!! $md($m->summary) !!}</p>@endif
                  </li>
                @endforeach
              </ul>
            @endif
            @break

          @case('projects')
            @if ($cards->isNotEmpty())
              <div class="pro-grid pro-projects">
                @foreach ($cards as $p)
                  <article class="pro-card pro-project @if ($p->is_featured) is-featured @endif pro-reveal">
                    <div class="pro-thumb">
                      @if ($p->thumbnail)
                        <img src="{{ $p->thumbnail->url() }}" alt="" loading="lazy" decoding="async" width="640" height="400">
                      @else
                        <span class="pro-thumb-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($p->name, 0, 1)) }}</span>
                      @endif
                    </div>
                    <div class="pro-project-body">
                      <p class="pro-kicker">
                        @if ($p->is_featured)<span class="pro-pill">{!! $icon('star') !!}Featured</span>@endif
                        {{ collect([$p->category, $p->period()])->filter()->implode(' · ') }}
                      </p>
                      <h3 class="pro-card-title"><a class="pro-title-link" href="{{ route('projects.show', ['project' => $p]) }}">{{ $p->name }}</a></h3>
                      @if ($p->tagline)<p>{{ $p->tagline }}</p>@elseif ($p->description)<p>{{ Str::limit(strip_tags($md($p->description)), 180) }}</p>@endif
                      @if ($p->technologies->isNotEmpty())
                        <ul class="pro-tags" aria-label="Technologies">
                          @foreach ($p->technologies->take(6) as $tech)<li>{{ $tech->name }}</li>@endforeach
                          @if ($p->technologies->count() > 6)<li>+{{ $p->technologies->count() - 6 }}<span class="pro-sr"> more</span></li>@endif
                        </ul>
                      @endif
                      <p class="pro-card-links">
                        <a href="{{ route('projects.show', ['project' => $p]) }}">Case study<span class="pro-sr">: {{ $p->name }}</span>{!! $icon('arrow') !!}</a>
                        @if ($p->live_url)<a href="{{ $p->live_url }}" target="_blank" rel="noopener">Live demo<span class="pro-sr"> of {{ $p->name }} (opens in a new tab)</span>{!! $icon('external') !!}</a>@endif
                        @if ($p->repo_url)<a href="{{ $p->repo_url }}" target="_blank" rel="noopener">Source<span class="pro-sr"> of {{ $p->name }} (opens in a new tab)</span>{!! $icon('code') !!}</a>@endif
                      </p>
                    </div>
                  </article>
                @endforeach
              </div>
            @endif
            @if ($highlights->isNotEmpty())
              <h3 class="pro-h3">Key achievements</h3>
              <ul class="pro-grid pro-grid-2 pro-plain">
                @foreach ($highlights as $h)
                  <li class="pro-card pro-mini pro-reveal">
                    <p class="pro-strong">{!! $icon('award') !!}{{ $h->title }}</p>
                    @if ($h->summary)<p class="pro-muted">{!! $md($h->summary) !!}</p>@endif
                    @if ($h->project)<p class="pro-small"><a href="{{ route('projects.show', ['project' => $h->project]) }}">{{ $h->project->name }}</a></p>@endif
                  </li>
                @endforeach
              </ul>
            @endif
            @break

          @case('education')
            @if ($education->isNotEmpty())
              <div class="pro-grid pro-grid-2">
                @foreach ($education as $ed)
                  <article class="pro-card pro-edu pro-reveal">
                    <span class="pro-badge-icon">{!! $icon('cap') !!}</span>
                    <div>
                      <h3 class="pro-card-title">{{ collect([$ed->degree, $ed->field])->filter()->implode(', ') ?: $ed->institution }}</h3>
                      @if ($ed->degree || $ed->field)<p class="pro-org">{{ $ed->institution }}</p>@endif
                      @if ($ed->period() || $ed->location)
                        <ul class="pro-meta">
                          @if ($ed->period())<li>{!! $icon('calendar') !!}{{ $ed->period() }}</li>@endif
                          @if ($ed->location)<li>{!! $icon('pin') !!}{{ $ed->location }}</li>@endif
                        </ul>
                      @endif
                      @if ($ed->description)<p>{!! $md($ed->description) !!}</p>@endif
                    </div>
                  </article>
                @endforeach
              </div>
            @endif
            @if ($certifications->isNotEmpty())
              <h3 class="pro-h3">Certifications</h3>
              <ul class="pro-grid pro-grid-2 pro-plain">
                @foreach ($certifications as $cert)
                  <li class="pro-card pro-cert pro-reveal">
                    <span class="pro-badge-icon">{!! $icon('award') !!}</span>
                    <div>
                      <p class="pro-strong">@if ($cert->credential_url)<a href="{{ $cert->credential_url }}" target="_blank" rel="noopener">{{ $cert->name }}<span class="pro-sr"> (opens in a new tab)</span></a>@else{{ $cert->name }}@endif</p>
                      @if ($cert->issuer || $cert->issued_on)<p class="pro-muted">{{ collect([$cert->issuer, $cert->issued_on?->format('M Y')])->filter()->implode(' · ') }}</p>@endif
                    </div>
                  </li>
                @endforeach
              </ul>
            @endif
            @break

          @case('writing')
            @if ($hasBlog)
              <div class="pro-grid pro-grid-3">
                @foreach ($posts->take(3) as $post)
                  <article class="pro-card pro-post pro-reveal">
                    <p class="pro-kicker">@if ($post->published_at)<time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('j M Y') }}</time>@endif @if ($post->category)· {{ $post->category }}@endif</p>
                    <h3 class="pro-card-title"><a class="pro-title-link" href="{{ $post->url() }}">{{ $post->title }}</a></h3>
                    @if ($post->excerpt)<p class="pro-muted">{{ Str::limit($post->excerpt, 150) }}</p>@endif
                  </article>
                @endforeach
              </div>
            @endif
            <p class="pro-more">
              @if ($hasBlog)<a class="pro-btn pro-btn-ghost" href="{{ route('blog.index') }}">{!! $icon('doc') !!}All articles</a>@endif
              @if ($hasNotes)<a class="pro-btn pro-btn-ghost" href="{{ route('notes.index') }}">{!! $icon('doc') !!}Notes ({{ $notes->count() }})</a>@endif
            </p>
            @break

          @case('contact')
            <div class="pro-contact @if (session('contact_sent') || $profile->contact_form_enabled) has-form @endif">
              <div class="pro-contact-info">
                <p class="pro-lead">
                  @if ($profile->open_to_work)I'm open to new roles, contracts and collaborations.@else I'm always happy to talk about interesting work.@endif
                  @if ($profile->contact_form_enabled) Send a message and I'll get back to you.@endif
                </p>
                @if ($profile->email || ($profile->show_phone && $profile->phone) || $profile->location)
                  <ul class="pro-contact-list">
                    @if ($profile->email)<li>{!! $icon('mail') !!}<a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a></li>@endif
                    @if ($profile->show_phone && $profile->phone)<li>{!! $icon('phone') !!}<a href="tel:{{ preg_replace('/[^\d+]/', '', $profile->phone) }}">{{ $profile->phone }}</a></li>@endif
                    @if ($profile->location)<li>{!! $icon('pin') !!}<span>{{ $profile->location }}</span></li>@endif
                  </ul>
                @endif
                @if ($links->isNotEmpty())
                  <ul class="pro-social" aria-label="Social profiles">
                    @foreach ($links as $link)
                      <li><a href="{{ $link->url }}" target="_blank" rel="me noopener">{!! $icon($socialIcon($link)) !!}{{ $link->label ?: Str::headline($link->platform) }}<span class="pro-sr"> (opens in a new tab)</span></a></li>
                    @endforeach
                  </ul>
                @endif
              </div>

              @if (session('contact_sent') || $profile->contact_form_enabled)
              <div class="pro-contact-form">
              @if (session('contact_sent'))
                <p class="pro-notice" role="status">{!! $icon('check') !!}Thanks — your message is on its way. I'll reply soon.</p>
              @endif

              @if ($profile->contact_form_enabled)
                <form class="pro-card pro-form" method="post" action="{{ route('contact.store') }}" novalidate>
                  @csrf
                  @if ($errors->any())
                    <p class="pro-error" role="alert">Please fix the highlighted fields below.</p>
                  @endif
                  <div class="pro-hp" aria-hidden="true">
                    <label for="c-website">Leave this field empty</label>
                    <input id="c-website" type="text" name="website" tabindex="-1" autocomplete="off">
                  </div>
                  <div class="pro-form-row">
                    @foreach ([['name', 'Name', 'text', 'name'], ['email', 'Email', 'email', 'email']] as [$field, $label, $type, $auto])
                      <div class="pro-field">
                        <label for="c-{{ $field }}">{{ $label }}</label>
                        <input id="c-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" autocomplete="{{ $auto }}" required
                          @error($field) aria-invalid="true" aria-describedby="c-{{ $field }}-err" @enderror>
                        @error($field)<p id="c-{{ $field }}-err" class="pro-field-error">{{ $message }}</p>@enderror
                      </div>
                    @endforeach
                  </div>
                  <div class="pro-field">
                    <label for="c-subject">Subject <span class="pro-muted">(optional)</span></label>
                    <input id="c-subject" type="text" name="subject" value="{{ old('subject') }}" autocomplete="off"
                      @error('subject') aria-invalid="true" aria-describedby="c-subject-err" @enderror>
                    @error('subject')<p id="c-subject-err" class="pro-field-error">{{ $message }}</p>@enderror
                  </div>
                  <div class="pro-field">
                    <label for="c-message">Message</label>
                    <textarea id="c-message" name="message" rows="6" required @error('message') aria-invalid="true" aria-describedby="c-message-err" @enderror>{{ old('message') }}</textarea>
                    @error('message')<p id="c-message-err" class="pro-field-error">{{ $message }}</p>@enderror
                  </div>
                  <button class="pro-btn pro-btn-primary" type="submit">{!! $icon('mail') !!}Send message</button>
                </form>
              @endif
              </div>
              @endif
            </div>
            @break
        @endswitch
      </div>
    </section>

    @if ($item['key'] === $quotesAfter && $testimonials->isNotEmpty())
      <section id="testimonials" class="pro-section pro-section-testimonials" aria-labelledby="testimonials-h">
        <div class="pro-wrap">
          <header class="pro-section-head">
            <p class="pro-eyebrow">Testimonials</p>
            <h2 id="testimonials-h" class="pro-h2">What people say</h2>
          </header>
          <div class="pro-grid pro-grid-2">
            @foreach ($testimonials as $t)
              <figure class="pro-card pro-quote pro-reveal">
                <span class="pro-quote-mark">{!! $icon('quote') !!}</span>
                <blockquote><p>{{ $t->quote }}</p></blockquote>
                <figcaption><strong>{{ $t->name }}</strong>@if ($t->position || $t->company)<span>{{ collect([$t->position, $t->company])->filter()->implode(', ') }}</span>@endif</figcaption>
              </figure>
            @endforeach
          </div>
        </div>
      </section>
    @endif
  @endforeach
@endsection
