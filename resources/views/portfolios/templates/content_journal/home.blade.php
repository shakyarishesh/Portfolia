@php
    use Illuminate\Support\Str;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $block = fn (?string $text) => Str::markdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $now = trim((string) ($settings['now'] ?? ''));
    $uses = trim((string) ($settings['uses'] ?? ''));
    $latest = $posts->take((int) ($settings['home_posts'] ?? 5));
    $keys = $sections->pluck('key');
    $work = $sections->filter(fn ($s) => in_array($s->key, ['projects', 'experience', 'skills', 'education'], true));
    $contact = $sections->firstWhere('key', 'contact');
    $label = fn ($s) => $s->nav_label ?: $s->title;

    // "On this page" index, in render order.
    $index = collect()
        ->when($now !== '', fn ($c) => $c->put('now', 'Now'))
        ->when($hasBlog, fn ($c) => $c->put('writing', 'Writing'))
        ->when($hasNotes, fn ($c) => $c->put('notes', 'Notes'))
        ->merge($work->mapWithKeys(fn ($s) => [$s->key => $label($s)]))
        ->when($uses !== '', fn ($c) => $c->put('uses', 'Uses'))
        ->when($contact, fn ($c) => $c->put('contact', $label($contact)));
@endphp
@extends('portfolios.templates.content_journal.layout', ['navBlog' => $hasBlog, 'navNotes' => $hasNotes, 'meta' => []])

@push('head')
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Person',
      'name' => $profile->full_name,
      'jobTitle' => $profile->headline ?: null,
      'url' => route('home'),
      'image' => $profile->avatarSrc(),
      'email' => $profile->email ? 'mailto:'.$profile->email : null,
      'address' => $profile->location ?: null,
      'worksFor' => $currentJob ? ['@type' => 'Organization', 'name' => $currentJob->company] : null,
      'sameAs' => $links->pluck('url')->all() ?: null,
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('main')
  <header class="cj-intro">
    @if ($profile->avatarSrc())
      <img class="cj-avatar" src="{{ $profile->avatarSrc() }}" alt="" width="72" height="72">
    @endif
    <h1>{{ $profile->full_name }}</h1>
    @if ($profile->headline)<p class="cj-headline">{{ $profile->headline }}</p>@endif
    @if ($profile->intro)<p class="cj-lede">{!! $md($profile->intro) !!}</p>@endif
    @if ($keys->contains('home'))
      @foreach ($profile->aboutParagraphs() as $p)<p>{!! $md($p) !!}</p>@endforeach
    @endif
    <ul class="cj-facts">
      @if ($profile->open_to_work)<li class="cj-open">Open to new work</li>@endif
      @if ($currentJob)<li>{{ $currentJob->role }} at {{ $currentJob->company }}</li>@endif
      @if ($profile->location)<li>{{ $profile->location }}</li>@endif
      @foreach ($links as $link)<li><a href="{{ $link->url }}" rel="noopener me">{{ $link->label ?: $link->platform }}</a></li>@endforeach
      @if ($profile->resume)<li><a href="{{ route('resume') }}">Download CV</a></li>@endif
    </ul>
  </header>

  @if ($index->count() > 1)
    <nav class="cj-index" aria-label="On this page">
      <ol>@foreach ($index as $id => $name)<li><a href="#{{ $id }}">{{ $name }}</a></li>@endforeach</ol>
    </nav>
  @endif

  @if ($now !== '')
    <section id="now" class="cj-block cj-now" aria-labelledby="now-h">
      <h2 id="now-h">Now</h2>
      <div class="cj-prose">{!! $block($now) !!}</div>
    </section>
  @endif

  @if ($hasBlog)
    <section id="writing" class="cj-block" aria-labelledby="writing-h">
      <h2 id="writing-h">Writing</h2>
      <ol class="cj-entries">
        @foreach ($latest as $post)
          @include('portfolios.templates.content_journal.partials.article-row', ['post' => $post])
        @endforeach
      </ol>
      <p class="cj-more"><a href="{{ route('blog.index') }}">All articles <span aria-hidden="true">→</span></a></p>
    </section>
  @endif

  @if ($hasNotes)
    <section id="notes" class="cj-block" aria-labelledby="notes-h">
      <h2 id="notes-h">Notes</h2>
      <p class="cj-sub">Evergreen pages I keep revising.</p>
      @include('portfolios.templates.content_journal.partials.note-list', ['notes' => $notes])
      <p class="cj-more"><a href="{{ route('notes.index') }}">Browse all notes <span aria-hidden="true">→</span></a></p>
    </section>
  @endif

  <div id="work">
    @foreach ($work as $section)
      <section id="{{ $section->key }}" class="cj-block" aria-labelledby="{{ $section->key }}-h">
        <h2 id="{{ $section->key }}-h">{{ $label($section) }}</h2>
        @if ($section->title && $section->title !== $label($section))<p class="cj-sub">{{ $section->title }}</p>@endif

        @switch($section->key)
          @case('projects')
            @if ($projects->isNotEmpty())<ul class="cj-projects">
              @foreach ($projects->sortByDesc('is_featured') as $project)
                <li>
                  <h3><a href="{{ route('projects.show', ['project' => $project]) }}">{{ $project->name }}</a>@if ($project->is_featured) <span class="cj-badge">Featured</span>@endif</h3>
                  @if ($project->tagline)<p>{{ $project->tagline }}</p>@endif
                  <p class="cj-meta">{{ collect([$project->category, $project->period()])->filter()->implode(' · ') }}@if ($project->technologies->isNotEmpty())<span class="cj-tech"> — {{ $project->technologies->take(5)->pluck('name')->implode(', ') }}</span>@endif</p>
                </li>
              @endforeach
            </ul>@endif
            @if ($highlights->isNotEmpty())
              <details class="cj-fold"><summary>Highlights ({{ $highlights->count() }})</summary>
                <ul class="cj-plain">@foreach ($highlights as $hl)<li><strong>{{ $hl->title }}</strong>@if ($hl->summary) — {{ $hl->summary }}@endif</li>@endforeach</ul>
              </details>
            @endif
            @break

          @case('experience')
            @if ($experiences->isNotEmpty())<ol class="cj-roles">
              @foreach ($experiences as $job)
                <li>
                  <details>
                    <summary>
                      <span class="cj-role"><strong>{{ $job->role }}</strong> · {{ $job->company }}</span>
                      <span class="cj-period">{{ $job->period() }}</span>
                    </summary>
                    <div class="cj-role-body">
                      @if ($job->location)<p class="cj-meta">{{ $job->location }}</p>@endif
                      @if ($job->summary)<p>{!! $md($job->summary) !!}</p>@endif
                      @if ($job->highlights)<ul>@foreach ($job->highlights as $h)<li>{!! $md($h) !!}</li>@endforeach</ul>@endif
                      @if ($job->technologies->isNotEmpty())<p class="cj-meta">{{ $job->technologies->pluck('name')->implode(', ') }}</p>@endif
                      @if ($job->company_url)<p class="cj-meta"><a href="{{ $job->company_url }}" rel="noopener">{{ $job->company }} website</a></p>@endif
                    </div>
                  </details>
                </li>
              @endforeach
            </ol>@endif
            @if ($milestones->isNotEmpty())
              <details class="cj-fold"><summary>Milestones ({{ $milestones->count() }})</summary>
                <ul class="cj-plain">@foreach ($milestones as $m)<li>@if ($m->date_label)<span class="cj-period">{{ $m->date_label }}</span> @endif{{ $m->title }}</li>@endforeach</ul>
              </details>
            @endif
            @break

          @case('skills')
            <dl class="cj-skills">
              @foreach ($skillCategories as $cat)
                <dt>{{ $cat->name }}</dt>
                <dd>{{ $cat->skills->pluck('name')->implode(', ') }}</dd>
              @endforeach
            </dl>
            @break

          @case('education')
            <ul class="cj-plain">
              @foreach ($education as $ed)
                <li><strong>{{ $ed->institution }}</strong>@if ($ed->degree || $ed->field) — {{ collect([$ed->degree, $ed->field])->filter()->implode(', ') }}@endif @if ($ed->period())<span class="cj-period">{{ $ed->period() }}</span>@endif</li>
              @endforeach
              @foreach ($certifications as $cert)
                <li>@if ($cert->credential_url)<a href="{{ $cert->credential_url }}" rel="noopener">{{ $cert->name }}</a>@else{{ $cert->name }}@endif @if ($cert->issuer)— {{ $cert->issuer }}@endif</li>
              @endforeach
            </ul>
            @break
        @endswitch
      </section>
    @endforeach
  </div>

  @if ($uses !== '')
    <section id="uses" class="cj-block" aria-labelledby="uses-h">
      <h2 id="uses-h">Uses</h2>
      <div class="cj-prose">{!! $block($uses) !!}</div>
    </section>
  @endif

  @if ($contact)
    <section id="contact" class="cj-block" aria-labelledby="contact-h">
      <h2 id="contact-h">{{ $label($contact) }}</h2>
      @if ($contact->title && $contact->title !== $label($contact))<p class="cj-sub">{{ $contact->title }}</p>@endif
      <p>
        @if ($profile->email)Write to <a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a>@endif
        @if ($profile->show_phone && $profile->phone)@if ($profile->email) or call @else Call @endif<a href="tel:{{ preg_replace('/[^0-9+]/', '', $profile->phone) }}">{{ $profile->phone }}</a>@endif
      </p>

      @if ($profile->contact_form_enabled)
        <form class="cj-form" method="post" action="{{ route('contact.store') }}" novalidate>
          @csrf
          @if (session('contact_sent'))
            <p class="cj-ok" role="status">Thanks — your message was sent.</p>
          @endif
          @if ($errors->any())
            <ul class="cj-errors" role="alert">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          @endif
          <div class="cj-hp" aria-hidden="true"><label for="cj-website">Website</label><input id="cj-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>
          <p><label for="cj-name">Name</label><input id="cj-name" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" @error('name') aria-invalid="true" @enderror></p>
          <p><label for="cj-email">Email</label><input id="cj-email" type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email" @error('email') aria-invalid="true" @enderror></p>
          <p><label for="cj-subject">Subject <span class="cj-meta">(optional)</span></label><input id="cj-subject" name="subject" value="{{ old('subject') }}" maxlength="150"></p>
          <p><label for="cj-message">Message</label><textarea id="cj-message" name="message" rows="5" required maxlength="5000" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea></p>
          <button type="submit" class="cj-button">Send message</button>
        </form>
      @endif
    </section>
  @endif
@endsection
