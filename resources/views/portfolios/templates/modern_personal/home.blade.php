@php
    use Illuminate\Support\Str;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $page = 'home';
    $newTab = '<span class="mp-sr"> (opens in a new tab)</span>';

    // Selected work: featured first, then admin order (sortBy is stable); the rest go under "More projects".
    $ordered = $projects->sortBy(fn ($p) => $p->is_featured ? 0 : 1)->values();
    $selected = $ordered->take((int) ($settings['featured_count'] ?? 4));
    $more = $ordered->slice($selected->count())->values();
    $thumbs = $selected->isNotEmpty() && $selected->every(fn ($p) => $p->thumbnail); // only when every row can have one
    $year = fn ($p) => $p->is_current ? 'Now' : ($p->end_date ?? $p->start_date)?->format('Y');

    $about = $profile->aboutParagraphs();
    $phone = $profile->show_phone && $profile->phone ? $profile->phone : null;
    $hasContact = $profile->email || $phone || $links->isNotEmpty() || $profile->contact_form_enabled;

    // Admin order and labels; "home" is the About block (the intro is always shown); Writing slots in before Contact.
    $blocks = $sections->filter(fn ($s) => match ($s->key) {
        'home' => $about || $expertise->isNotEmpty() || $testimonials->isNotEmpty(),
        'contact' => $hasContact,
        default => true,
    })->map(fn ($s) => [
        'key' => $s->key,
        'id' => $s->key === 'home' ? 'about' : $s->key,
        'label' => $s->key === 'home' ? 'About' : ($s->nav_label ?: Str::headline($s->key)),
        'title' => $s->title ?: ($s->nav_label ?: Str::headline($s->key)),
    ])->values();
    if ($hasBlog || $hasNotes) {
        $writing = ['key' => 'writing', 'id' => 'writing', 'label' => 'Writing', 'title' => 'Writing'];
        $at = $blocks->search(fn ($b) => $b['key'] === 'contact');
        $blocks = $at === false ? $blocks->push($writing) : $blocks->splice(0, $at)->push($writing)->concat($blocks)->values();
    }
    $shown = $blocks->pluck('key');
    $nav = $blocks->map(fn ($b) => ['#'.$b['id'], $b['label']])->all();
@endphp
@extends('portfolios.templates.modern_personal.layout')

@section('head')
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Person',
      'name' => $profile->full_name,
      'jobTitle' => $profile->headline ?: null,
      'url' => route('home'),
      'image' => $profile->avatarSrc(),
      'email' => $profile->email ? 'mailto:'.$profile->email : null,
      'address' => $profile->location ?: null,
      'worksFor' => $currentJob ? ['@type' => 'Organization', 'name' => $currentJob->company, 'url' => $currentJob->company_url] : null,
      'alumniOf' => $education->map(fn ($e) => ['@type' => 'EducationalOrganization', 'name' => $e->institution])->all() ?: null,
      'sameAs' => $links->pluck('url')->all() ?: null,
      'knowsAbout' => $skillCategories->flatMap->skills->pluck('name')->all() ?: null,
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('body')
  <section class="mp-intro mp-grid" aria-labelledby="intro-h">
    <div class="mp-track">
      @if ($profile->avatarSrc())
        <img class="mp-avatar" src="{{ $profile->avatarSrc() }}" alt="Portrait of {{ $profile->full_name }}" width="64" height="64">
      @endif
      <ul class="mp-facts">
        @if ($profile->location)<li>{{ $profile->location }}</li>@endif
        @if ($currentJob)<li>{{ $currentJob->role }}, {{ $currentJob->company }}</li>@endif
        @if ($profile->open_to_work)<li class="mp-accent">Open to new work</li>@endif
      </ul>
    </div>
    <div>
      <h1 id="intro-h" class="mp-name">{{ $profile->full_name }}</h1>
      @if ($profile->headline)<p class="mp-role">{{ $profile->headline }}</p>@endif
      @if ($profile->intro)<p class="mp-statement">{!! $md($profile->intro) !!}</p>@endif
      @if ($shown->contains('projects') || $shown->contains('contact') || $profile->resume)
        <p class="mp-ctas">
          @if ($shown->contains('contact'))<a class="mp-btn" href="#contact">Get in touch</a>@endif
          @if ($shown->contains('projects'))<a href="#projects">Selected work</a>@endif
          @if ($profile->resume)<a href="{{ route('resume') }}">Résumé (PDF)</a>@endif
        </p>
      @endif
    </div>
  </section>

  @foreach ($blocks as $b)
    <section id="{{ $b['id'] }}" class="mp-sec mp-grid mp-sec-{{ $b['key'] }}" aria-labelledby="{{ $b['id'] }}-h">
      <h2 id="{{ $b['id'] }}-h" class="mp-h2"><span class="mp-idx" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span> {{ $b['title'] }}</h2>
      <div class="mp-body">
        @switch($b['key'])
          @case('home')
            @foreach ($about as $para)<p class="mp-prose">{!! $md($para) !!}</p>@endforeach
            @if ($expertise->isNotEmpty())
              <h3 class="mp-h3">Focus</h3>
              <dl class="mp-pairs">
                @foreach ($expertise as $area)
                  <div><dt>{{ $area->title }}</dt>@if ($area->description)<dd>{!! $md($area->description) !!}</dd>@endif</div>
                @endforeach
              </dl>
            @endif
            @if ($testimonials->isNotEmpty())
              <h3 class="mp-h3">Kind words</h3>
              @foreach ($testimonials as $t)
                <figure class="mp-quote">
                  <blockquote><p>{{ $t->quote }}</p></blockquote>
                  <figcaption>{{ collect([$t->name, $t->position, $t->company])->filter()->implode(', ') }}</figcaption>
                </figure>
              @endforeach
            @endif
            @break

          @case('projects')
            @if ($selected->isNotEmpty())
              <ol class="mp-work">
                @foreach ($selected as $p)
                  <li class="mp-work-row">
                    <div class="mp-work-year">
                      {{ $year($p) }}
                      @if ($thumbs)<img class="mp-thumb" src="{{ $p->thumbnail->url() }}" alt="" loading="lazy" width="160" height="100">@endif
                    </div>
                    <div class="mp-work-main">
                      <h3 class="mp-h3"><a href="{{ route('projects.show', ['project' => $p]) }}">{{ $p->name }}</a></h3>
                      @if ($p->tagline)<p>{{ $p->tagline }}</p>@elseif ($p->description)<p>{{ Str::limit(strip_tags($md($p->description)), 200) }}</p>@endif
                      @if ($p->live_url || $p->repo_url)
                        <p class="mp-links">
                          @if ($p->live_url)<a href="{{ $p->live_url }}" target="_blank" rel="noopener">Live<span class="mp-sr"> site for {{ $p->name }}</span>{!! $newTab !!} ↗</a>@endif
                          @if ($p->repo_url)<a href="{{ $p->repo_url }}" target="_blank" rel="noopener">Source<span class="mp-sr"> of {{ $p->name }}</span>{!! $newTab !!} ↗</a>@endif
                        </p>
                      @endif
                    </div>
                    <p class="mp-work-role">@if ($p->role || $p->experience)<span class="mp-sr">Role: </span>{{ collect([$p->role, $p->experience?->company])->filter()->implode(', ') }}@endif</p>
                    <p class="mp-work-stack">@if ($p->technologies->isNotEmpty())<span class="mp-sr">Stack: </span>{{ $p->technologies->pluck('name')->implode(', ') }}@endif</p>
                  </li>
                @endforeach
              </ol>
            @endif
            @if ($more->isNotEmpty())
              <h3 class="mp-h3 mp-sub">More projects</h3>
              <ul class="mp-rows">
                @foreach ($more as $p)
                  <li><a href="{{ route('projects.show', ['project' => $p]) }}">{{ $p->name }}</a>@if ($p->tagline)<span class="mp-muted"> — {{ $p->tagline }}</span>@endif<span class="mp-date">{{ $year($p) }}</span></li>
                @endforeach
              </ul>
            @endif
            @if ($highlights->isNotEmpty())
              <h3 class="mp-h3 mp-sub">Notable work</h3>
              <ul class="mp-plain">
                @foreach ($highlights as $h)
                  <li>
                    <p class="mp-strong">{{ $h->title }}@if ($h->project)<span class="mp-muted"> — <a href="{{ route('projects.show', ['project' => $h->project]) }}">{{ $h->project->name }}</a></span>@endif</p>
                    @if ($h->summary)<p class="mp-muted">{!! $md($h->summary) !!}</p>@endif
                  </li>
                @endforeach
              </ul>
            @endif
            @break

          @case('experience')
            <ol class="mp-list">
              @foreach ($experiences as $job)
                <li class="mp-entry">
                  <p class="mp-date">{{ $job->period() }}</p>
                  <div>
                    <h3 class="mp-h3">{{ $job->role }}<span class="mp-muted">,
                      @if ($job->company_url)<a href="{{ $job->company_url }}" target="_blank" rel="noopener">{{ $job->company }}{!! $newTab !!}</a>@else{{ $job->company }}@endif</span>
                    </h3>
                    @if ($job->location)<p class="mp-meta">{{ $job->location }}</p>@endif
                    @if ($job->highlights)
                      <ul class="mp-points">@foreach (array_slice($job->highlights, 0, 3) as $point)<li>{!! $md($point) !!}</li>@endforeach</ul>
                    @elseif ($job->summary)
                      <p>{!! $md($job->summary) !!}</p>
                    @endif
                    @if ($job->technologies->isNotEmpty())<p class="mp-meta">{{ $job->technologies->pluck('name')->implode(' · ') }}</p>@endif
                  </div>
                </li>
              @endforeach
              @foreach ($milestones as $m)
                <li class="mp-entry">
                  <p class="mp-date">{{ $m->date_label }}</p>
                  <div><h3 class="mp-h3">{{ $m->title }}</h3>@if ($m->summary)<p class="mp-muted">{!! $md($m->summary) !!}</p>@endif</div>
                </li>
              @endforeach
            </ol>
            @break

          @case('skills')
            <dl class="mp-pairs mp-skills">
              @foreach ($skillCategories as $cat)
                <div>
                  <dt>{{ $cat->name }}</dt>
                  <dd><ul class="mp-tags">@foreach ($cat->skills as $skill)<li>{{ $skill->name }}</li>@endforeach</ul></dd>
                </div>
              @endforeach
            </dl>
            @break

          @case('education')
            <ol class="mp-list">
              @foreach ($education as $ed)
                <li class="mp-entry">
                  <p class="mp-date">{{ $ed->period() }}</p>
                  <div>
                    <h3 class="mp-h3">{{ collect([$ed->degree, $ed->field])->filter()->implode(', ') ?: $ed->institution }}</h3>
                    @if ($ed->degree || $ed->field)<p class="mp-meta">{{ $ed->institution }}</p>@endif
                  </div>
                </li>
              @endforeach
              @foreach ($certifications as $cert)
                <li class="mp-entry">
                  <p class="mp-date">{{ $cert->issued_on?->format('Y') }}</p>
                  <div>
                    <h3 class="mp-h3">@if ($cert->credential_url)<a href="{{ $cert->credential_url }}" target="_blank" rel="noopener">{{ $cert->name }}{!! $newTab !!}</a>@else{{ $cert->name }}@endif</h3>
                    <p class="mp-meta">{{ collect(['Certification', $cert->issuer])->filter()->implode(' · ') }}</p>
                  </div>
                </li>
              @endforeach
            </ol>
            @break

          @case('writing')
            <ul class="mp-rows">
              @foreach (($hasBlog ? $posts : $notes)->take(5) as $post)
                <li class="mp-post-row"><a href="{{ $post->url() }}">{{ $post->title }}</a>
                  @if ($post->published_at && ! $post->isNote())<time class="mp-date" datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('M Y') }}</time>@endif
                </li>
              @endforeach
            </ul>
            <p class="mp-links">
              @if ($hasBlog)<a href="{{ route('blog.index') }}">All writing →</a>@endif
              @if ($hasNotes)<a href="{{ route('notes.index') }}">Notes ({{ $notes->count() }}) →</a>@endif
            </p>
            @break

          @case('contact')
            @if ($profile->email)<p class="mp-statement"><a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a></p>@endif
            @if ($phone)<p><a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a></p>@endif
            @if ($links->isNotEmpty())
              <ul class="mp-inline" aria-label="Elsewhere">
                @foreach ($links as $link)
                  <li><a href="{{ $link->url }}" rel="me noopener" target="_blank">{{ $link->label ?: Str::headline($link->platform) }}{!! $newTab !!}</a></li>
                @endforeach
              </ul>
            @endif

            @if (session('contact_sent'))
              <p class="mp-notice" role="status">Thanks — your message is on its way. I'll reply soon.</p>
            @endif

            @if ($profile->contact_form_enabled)
              <form class="mp-form" method="post" action="{{ route('contact.store') }}" novalidate>
                @csrf
                @if ($errors->any())
                  <p class="mp-error" role="alert">Please fix the highlighted fields below.</p>
                @endif
                <div class="mp-hp" aria-hidden="true">
                  <label for="c-website">Leave this field empty</label>
                  <input id="c-website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>
                @foreach ([
                    ['name', 'Name', 'text', 'name', true],
                    ['email', 'Email', 'email', 'email', true],
                    ['subject', 'Subject (optional)', 'text', 'off', false],
                ] as [$field, $label, $type, $auto, $required])
                  <div class="mp-field mp-field-{{ $field }}">
                    <label for="c-{{ $field }}">{{ $label }}</label>
                    <input id="c-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" autocomplete="{{ $auto }}" @if ($required) required @endif
                      @error($field) aria-invalid="true" aria-describedby="c-{{ $field }}-err" @enderror>
                    @error($field)<p id="c-{{ $field }}-err" class="mp-field-error">{{ $message }}</p>@enderror
                  </div>
                @endforeach
                <div class="mp-field mp-field-message">
                  <label for="c-message">Message</label>
                  <textarea id="c-message" name="message" rows="6" required @error('message') aria-invalid="true" aria-describedby="c-message-err" @enderror>{{ old('message') }}</textarea>
                  @error('message')<p id="c-message-err" class="mp-field-error">{{ $message }}</p>@enderror
                </div>
                <p><button class="mp-btn" type="submit">Send message</button></p>
              </form>
            @endif
            @break
          @default
            @include('portfolios.partials.text-section', ['key' => $b['key']])
        @endswitch
      </div>
    </section>
  @endforeach
@endsection
