{{-- /create step 1: what kind of portfolio. A GET form to /create?type=…, so the next step's URL is shareable and Back works. --}}
@extends('site.layout')
@section('title', 'Choose your portfolio type · '.config('portfolio.platform_name'))
@section('description', 'Pick the kind of portfolio you want — technical, creative, business, leadership, education, general or a mix — and get sections made for your work.')
@push('head')
  <link rel="stylesheet" href="{{ asset('site/apply.css') }}?v={{ filemtime(public_path('site/apply.css')) }}">
@endpush

@section('content')
@php
    $picked = old('type', request('type'));
    $pickedDisciplines = (array) old('disciplines', request('disciplines', []));
@endphp
<div class="wrap apply">
  @include('site.apply._progress', ['current' => 'type'])
  <h1>What kind of portfolio are you creating?</h1>
  <p class="lead">Your choice decides which sections and project fields you get — a designer gets galleries and creative process, a teacher gets courses and learning outcomes. You can change it later without losing anything.</p>

  @if ($errors->any())
    <div class="error-summary" role="alert" tabindex="-1">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="get" action="{{ route('apply.create') }}" class="type-form">
    <fieldset>
      <legend class="sr-only">Portfolio type</legend>
      <div class="type-grid">
        @foreach ($types as $t)
          <label class="type-card" for="type-{{ $t->slug }}">
            <input type="radio" id="type-{{ $t->slug }}" name="type" value="{{ $t->slug }}" required @checked($picked === $t->slug)
                   aria-describedby="type-{{ $t->slug }}-desc">
            <span class="type-check" aria-hidden="true"></span>
            <span class="type-icon" aria-hidden="true">{{ $t->icon ?: '📁' }}</span>
            <span class="type-name">{{ $t->name }}</span>
            <span class="type-desc" id="type-{{ $t->slug }}-desc">{{ $t->description }}</span>
            @if ($t->categories)
              <span class="type-cats">
                @foreach (array_slice($t->categories, 0, 4) as $c)<span>{{ $c }}</span>@endforeach
                @if (count($t->categories) > 4)<span title="{{ implode(', ', array_slice($t->categories, 4)) }}">+{{ count($t->categories) - 4 }} more</span>@endif
              </span>
            @endif
            @if ($t->example)<span class="type-example"><b>Showcase:</b> {{ $t->example }}</span>@endif
          </label>
        @endforeach
      </div>
    </fieldset>

    @if ($types->contains(fn ($t) => $t->isHybrid()))
      <fieldset class="disciplines" id="disciplines">
        <legend>Hybrid: which disciplines do you combine?</legend>
        <p class="help">Tick two or more. You get the sections and project fields of each, and every project can belong to one or several.</p>
        <div class="discipline-choices">
          @foreach ($disciplineOptions as $slug => $name)
            <label class="check"><input type="checkbox" name="disciplines[]" value="{{ $slug }}" @checked(in_array($slug, $pickedDisciplines, true))> {{ $name }}</label>
          @endforeach
        </div>
        @error('disciplines')<p class="error">{{ $message }}</p>@enderror
      </fieldset>
    @endif

    <div class="step-nav">
      <a class="btn" href="{{ route('root') }}">Back</a>
      <button class="btn btn-primary" type="submit">Continue</button>
    </div>
  </form>

  <section class="samples" aria-labelledby="samples-h">
    <h2 id="samples-h">Not sure? See a sample of each</h2>
    <p class="help">Example portfolios with made-up content — they open in a new tab.</p>
    <ul>
      @foreach ($types as $t)
        <li><a href="{{ route('demo.sample', $t) }}" target="_blank" rel="noopener">{{ $t->icon }} {{ $t->name }}<span class="sr-only"> sample (opens in a new tab)</span></a></li>
      @endforeach
    </ul>
  </section>
</div>
@endsection
