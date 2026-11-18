@extends('site.layout')

@section('title', config('portfolio.platform_name').' — your own portfolio website')
@section('description', 'Get your own portfolio website at '.url('/').'/your-username. Pick a template, send your details, and we publish it after a quick review. Try the demo first — nothing is saved.')

@push('head')
  <link rel="stylesheet" href="{{ asset('site/landing.css') }}?v={{ filemtime(public_path('site/landing.css')) }}">
@endpush

@php
  $demoTemplate = old('template', $demo['template'] ?? \App\Support\Templates::DEFAULT);
@endphp

@section('content')
  <section class="hero">
    <div class="wrap hero-inner">
      <p class="eyebrow">Portfolio websites, done for you</p>
      <h1>Your work, on your own page.</h1>
      <p class="lede">Get a polished portfolio at <span class="url-sample">{{ url('/') }}/<b>your-username</b></span> — pick a template, add your experience, projects and skills, and share one link.</p>
      <div class="cta-row">
        <a class="btn btn-primary btn-lg" href="{{ route('apply.create') }}">Create your portfolio</a>
        <a class="btn btn-lg" href="#demo">Try the demo</a>
      </div>
      <p class="muted small">The demo needs no account and saves nothing.</p>
    </div>
  </section>

  <section class="band" aria-labelledby="features-title">
    <div class="wrap">
      <h2 id="features-title">What you get</h2>
      <ul class="features">
        <li><h3>Your own address</h3><p>A clean, shareable link — <code>/your-username</code> — for your CV, LinkedIn and email signature.</p></li>
        <li><h3>{{ count($templates) }} designed templates</h3><p>From quiet and typographic to colourful and playful. Switch any time without re-entering anything.</p></li>
        <li><h3>Everything in one place</h3><p>About, experience, projects with case studies, skills, education, certificates and a contact section.</p></li>
        <li><h3>Search-friendly</h3><p>Fast pages with proper titles, descriptions and social previews, so your name finds your work.</p></li>
      </ul>
    </div>
  </section>

  <section class="band" aria-labelledby="how-title">
    <div class="wrap">
      <h2 id="how-title">How it works</h2>
      <ol class="steps">
        <li><h3>Fill in your details</h3><p>Choose a username and a template, then add your profile, experience, projects and skills.</p></li>
        <li><h3>We review it</h3><p>An admin checks the submission so every published portfolio stays genuine and tidy.</p></li>
        <li><h3>Go live</h3><p>Once approved, your portfolio is published at <code>{{ url('/') }}/your-username</code>.</p></li>
      </ol>
      <p><a class="btn btn-primary" href="{{ route('apply.create') }}">Start your application</a></p>
    </div>
  </section>

  <section class="band" id="templates" aria-labelledby="templates-title">
    <div class="wrap">
      <h2 id="templates-title">Templates</h2>
      <p class="muted">Every template shows the same content in its own style. Try any of them with your details in the demo below.</p>
      <ul class="gallery">
        @foreach ($templates as $key => $t)
          <li class="card">
            <img src="{{ asset($t['preview']) }}" alt="" width="320" height="200" loading="lazy">
            <div class="card-body">
              <h3>{{ $t['name'] }}</h3>
              <p>{{ $t['description'] }}</p>
            </div>
          </li>
        @endforeach
      </ul>
    </div>
  </section>

  <section class="band demo" id="demo" aria-labelledby="demo-title">
    <div class="wrap demo-grid">
      <div>
        <h2 id="demo-title">Try the demo</h2>
        <p>Enter a few details and see them in any template. Nothing is saved: your details stay only in this browser session and are gone when the session ends — or when you press <b>Clear</b>.</p>
        <p class="muted small">Like what you see? <a href="{{ route('apply.create') }}">Create your portfolio</a> to publish it for real.</p>
        @if ($demo)
          <p class="demo-actions">
            <a class="btn" href="{{ route('demo.show') }}">Open your demo</a>
          </p>
          <form method="post" action="{{ route('demo.destroy') }}">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Clear demo data</button></form>
        @endif
      </div>

      <form class="demo-form" method="post" action="{{ route('demo.store') }}" novalidate>
        @csrf
        @if ($errors->any())
          <div class="errors" role="alert"><p><b>Please fix the highlighted fields.</b></p></div>
        @endif

        <fieldset>
          <legend>About you</legend>
          <div class="field">
            <label for="d-name">Name <span aria-hidden="true">*</span></label>
            <input id="d-name" name="name" value="{{ old('name', $demo['name'] ?? '') }}" required maxlength="80" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="d-name-error" @enderror>
            @error('name')<p class="error" id="d-name-error">{{ $message }}</p>@enderror
          </div>
          <div class="grid-2">
            <div class="field">
              <label for="d-headline">Headline</label>
              <input id="d-headline" name="headline" value="{{ old('headline', $demo['headline'] ?? '') }}" maxlength="120" placeholder="e.g. Backend Developer">
              @error('headline')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
              <label for="d-location">Location</label>
              <input id="d-location" name="location" value="{{ old('location', $demo['location'] ?? '') }}" maxlength="80">
              @error('location')<p class="error">{{ $message }}</p>@enderror
            </div>
          </div>
          <div class="field">
            <label for="d-bio">Short bio</label>
            <textarea id="d-bio" name="bio" rows="3" maxlength="600">{{ old('bio', $demo['bio'] ?? '') }}</textarea>
            @error('bio')<p class="error">{{ $message }}</p>@enderror
          </div>
          <div class="grid-2">
            <div class="field">
              <label for="d-email">Email</label>
              <input id="d-email" name="email" type="email" value="{{ old('email', $demo['email'] ?? '') }}" maxlength="150" autocomplete="email" @error('email') aria-invalid="true" @enderror>
              @error('email')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
              <label for="d-skills">Skills</label>
              <input id="d-skills" name="skills" value="{{ old('skills', $demo['skills'] ?? '') }}" maxlength="400" aria-describedby="d-skills-help">
              <p class="help" id="d-skills-help">Comma-separated, up to {{ \App\Http\Controllers\DemoController::MAX_SKILLS }}.</p>
              @error('skills')<p class="error">{{ $message }}</p>@enderror
            </div>
          </div>
        </fieldset>

        @foreach ([0, 1] as $i)
          <fieldset>
            <legend>Project {{ $i + 1 }} <span class="muted">(optional)</span></legend>
            <div class="grid-2">
              <div class="field">
                <label for="d-p{{ $i }}-name">Name</label>
                <input id="d-p{{ $i }}-name" name="projects[{{ $i }}][name]" value="{{ old("projects.$i.name", $demo['projects'][$i]['name'] ?? '') }}" maxlength="80" @error("projects.$i.name") aria-invalid="true" @enderror>
                @error("projects.$i.name")<p class="error">{{ $message }}</p>@enderror
              </div>
              <div class="field">
                <label for="d-p{{ $i }}-url">Link</label>
                <input id="d-p{{ $i }}-url" name="projects[{{ $i }}][url]" type="url" value="{{ old("projects.$i.url", $demo['projects'][$i]['url'] ?? '') }}" maxlength="255" placeholder="https://" @error("projects.$i.url") aria-invalid="true" @enderror>
                @error("projects.$i.url")<p class="error">{{ $message }}</p>@enderror
              </div>
            </div>
            <div class="field">
              <label for="d-p{{ $i }}-desc">Short description</label>
              <textarea id="d-p{{ $i }}-desc" name="projects[{{ $i }}][description]" rows="2" maxlength="300">{{ old("projects.$i.description", $demo['projects'][$i]['description'] ?? '') }}</textarea>
              @error("projects.$i.description")<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
              <label for="d-p{{ $i }}-tech">Technologies</label>
              <input id="d-p{{ $i }}-tech" name="projects[{{ $i }}][technologies]" value="{{ old("projects.$i.technologies", $demo['projects'][$i]['technologies'] ?? '') }}" maxlength="200" placeholder="Comma-separated">
              @error("projects.$i.technologies")<p class="error">{{ $message }}</p>@enderror
            </div>
          </fieldset>
        @endforeach

        <div class="field">
          <label for="d-template">Template</label>
          <select id="d-template" name="template">
            @foreach ($templates as $key => $t)
              <option value="{{ $key }}" @selected($demoTemplate === $key)>{{ $t['name'] }}</option>
            @endforeach
          </select>
          <p class="help">You can switch templates from the demo page too.</p>
          @error('template')<p class="error">{{ $message }}</p>@enderror
        </div>

        <button class="btn btn-primary btn-lg" type="submit">See my demo portfolio</button>
      </form>
    </div>
  </section>

  <section class="band closing">
    <div class="wrap">
      <h2>Ready for the real thing?</h2>
      <p>Send your details once; we take care of the rest.</p>
      <a class="btn btn-primary btn-lg" href="{{ route('apply.create') }}">Create your portfolio</a>
    </div>
  </section>
@endsection
