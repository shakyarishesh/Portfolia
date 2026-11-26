@extends('site.layout')
@section('title', 'Create your portfolio · '.config('portfolio.platform_name'))
@section('description', 'Fill in your details once and get a portfolio website at your own address after a quick review.')
@push('head')
  <link rel="stylesheet" href="{{ asset('site/apply.css') }}?v={{ filemtime(public_path('site/apply.css')) }}">
@endpush
@push('scripts')
  <script src="{{ asset('site/apply.js') }}?v={{ filemtime(public_path('site/apply.js')) }}" defer></script>
@endpush

@section('content')
@php
    $typeQuery = ['type' => $type->slug, 'disciplines' => $disciplines ?: null];
    $hidden = fn () => '<input type="hidden" name="type" value="'.e($type->slug).'">'.collect($disciplines)->map(fn ($d) => '<input type="hidden" name="disciplines[]" value="'.e($d).'">')->implode('');
@endphp
<div class="wrap apply" data-stepper>
  @include('site.apply._progress', ['current' => 'basics', 'changeTypeUrl' => route('apply.create', ['step' => 'type'] + $typeQuery)])
  <h1>Create your {{ $type->name }} portfolio</h1>
  <p class="lead">
    <span class="type-chip">{{ $type->icon }} {{ $type->name }}@if ($disciplineNames): {{ implode(' + ', $disciplineNames) }}@endif</span>
    <a href="{{ route('apply.create', ['step' => 'type'] + $typeQuery) }}">Change type</a>
  </p>
  <p class="lead">Tell us about yourself once — we turn it into a portfolio website in the template you pick. Only your name, email, address and password are required; add as much of the rest as you like.</p>

  <section class="import-box" aria-labelledby="import-title" data-step="basics">
    <h2 id="import-title">Prefer to fill it in offline?</h2>
    <ol>
      <li><a href="{{ route('apply.template', $typeQuery) }}" download>Download the details template</a> — a plain text file with the fields of a {{ $type->name }} portfolio.</li>
      <li>Fill it in with any text editor (Notepad, TextEdit …). Notes in the file explain each field.</li>
      <li>Upload it here: the form below is filled in from your file so you can check it, add your photo and CV, and submit.</li>
    </ol>
    <form method="post" action="{{ route('apply.import') }}" enctype="multipart/form-data" class="import-form">
      @csrf
      {!! $hidden() !!}
      <label for="details-file">Your filled-in template</label>
      <input type="file" id="details-file" name="details" accept=".txt,text/plain" required>
      <button class="btn" type="submit">Fill in the form</button>
    </form>
    @if (session('import_warnings'))
      <div class="import-warnings" role="status">
        <p>Some parts of your file couldn't be used — please fill these in below:</p>
        <ul>@foreach (session('import_warnings') as $w)<li>{{ $w }}</li>@endforeach</ul>
      </div>
    @endif
  </section>

  @if ($errors->any())
    <div class="error-summary" role="alert" tabindex="-1">
      <h2>Please fix {{ $errors->count() === 1 ? 'this problem' : 'these '.$errors->count().' problems' }}</h2>
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      <p>Uploaded files are not kept after an error — choose your photo and CV again.</p>
    </div>
  @endif

  <form method="post" action="{{ route('apply.store') }}" enctype="multipart/form-data" id="apply-form">
    @csrf
    {!! $hidden() !!}
    <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <fieldset data-step="basics">
      <legend>Your address</legend>
      <div class="field">
        <label for="username">Username <span aria-hidden="true">*</span></label>
        <div class="url-input">
          <span aria-hidden="true">{{ url('/') }}/</span>
          <input id="username" name="username" value="{{ old('username') }}" required minlength="3" maxlength="40" pattern="{{ \App\Support\Username::PATTERN }}"
                 autocapitalize="none" autocomplete="off" spellcheck="false" aria-describedby="username-help @error('username') username-error @enderror" @error('username') aria-invalid="true" @enderror>
        </div>
        <p class="help" id="username-help">Your portfolio will live at {{ url('/') }}/<b>username</b>. 3–40 lower-case letters, digits and single hyphens.</p>
        @error('username')<p class="error" id="username-error">{{ $message }}</p>@enderror
      </div>

    </fieldset>

    <fieldset data-step="basics">
      <legend>Profile</legend>
      <div class="grid-2">
        @foreach ($profileFields as $name => $field)
          @include('site.apply._field', ['name' => $name, 'id' => "f-{$name}", 'errorKey' => $name, 'value' => old($name),
              'required' => in_array('required', $field['rules'], true)])
        @endforeach
        <div class="field">
          <label for="f-avatar">Profile photo</label>
          <input type="file" id="f-avatar" name="avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="f-avatar-help @error('avatar') f-avatar-error @enderror">
          <p class="help" id="f-avatar-help">JPG, PNG or WebP, at least 100×100 px, up to {{ round(config('portfolio.uploads.image_max_kb') / 1024) }} MB.</p>
          @error('avatar')<p class="error" id="f-avatar-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="f-resume">CV / résumé</label>
          <input type="file" id="f-resume" name="resume" accept="application/pdf" aria-describedby="f-resume-help @error('resume') f-resume-error @enderror">
          <p class="help" id="f-resume-help">PDF, up to {{ round(config('portfolio.uploads.resume_max_kb') / 1024) }} MB. Visitors can download it from your portfolio.</p>
          @error('resume')<p class="error" id="f-resume-error">{{ $message }}</p>@enderror
        </div>
      </div>
    </fieldset>

    <fieldset data-step="basics">
      <legend>Your login</legend>
      <p class="help">Choose a password so you can sign in later and keep editing your portfolio — sign in with your email, your username or your phone number.</p>
      <div class="grid-2">
        <div class="field">
          <label for="f-password">Password <span aria-hidden="true">*</span></label>
          <input type="password" id="f-password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="f-password-help @error('password') f-password-error @enderror" @error('password') aria-invalid="true" @enderror>
          <p class="help" id="f-password-help">At least 8 characters.</p>
          @error('password')<p class="error" id="f-password-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="f-password_confirmation">Confirm password <span aria-hidden="true">*</span></label>
          <input type="password" id="f-password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>
      </div>
    </fieldset>

    <fieldset class="templates" data-step="template">
      <legend>Template</legend>
      <p class="help">Designs that suit a {{ $type->name }} portfolio. You can switch later — your content stays the same.</p>
      <div class="template-choices">
        @foreach ($templates as $k => $t)
          <label class="template-choice">
            <input type="radio" name="template" value="{{ $k }}" @checked(old('template', array_key_first($templates)) === $k)>
            <img src="{{ asset($t['preview']) }}" alt="" width="320" height="200" loading="lazy">
            <span class="template-name">{{ $t['name'] }}</span>
            <span class="help">{{ $t['description'] }}</span>
          </label>
        @endforeach
      </div>
      @error('template')<p class="error">{{ $message }}</p>@enderror
    </fieldset>

    @foreach ($groups as $key => $group)
      @php $rows = old($key) ?: [[]]; @endphp
      <fieldset class="repeat" data-group data-step="details">
        <legend>{{ $group['label'] }}</legend>
        <p class="help">Optional — blank entries are skipped{{ $key === 'testimonials' ? '. Only include quotes the person agreed to share' : '' }}.</p>
        @error($key)<p class="error">{{ $message }}</p>@enderror
        <div class="repeat-rows" data-rows data-next="{{ count($rows) }}" data-max="{{ \App\Http\Controllers\ApplicationController::MAX_ROWS }}">
          @foreach (array_values($rows) as $i => $row)
            @include('site.apply._row', ['row' => (array) $row])
          @endforeach
        </div>
        <template data-row-template>@include('site.apply._row', ['i' => '__INDEX__', 'row' => []])</template>
        <button type="button" class="btn" data-add hidden>+ Add another {{ $group['singular'] }}</button>
      </fieldset>
    @endforeach

    <div class="submit" data-step="review">
      <h2>Preview &amp; submit</h2>
      <p>See your portfolio as visitors will, with the template you picked — it opens in a new tab and nothing is saved yet.</p>
      <p><button class="btn" type="submit" formaction="{{ route('apply.preview') }}" formtarget="_blank" formnovalidate>Preview my portfolio<span class="sr-only"> (opens in a new tab)</span></button></p>
      <p>An admin reviews every application before it goes live. Until it is approved your portfolio is not public; we may email you if something needs fixing. By submitting you confirm the information is accurate and yours to share.</p>
      <button class="btn btn-primary" type="submit">Submit for review</button>
    </div>

    {{-- Back / Continue between steps: shown by apply.js; without JavaScript the whole form is one page. --}}
    <div class="step-nav" data-step-nav hidden>
      <button class="btn" type="button" data-step-back>Back</button>
      <button class="btn btn-primary" type="button" data-step-next>Continue</button>
    </div>
  </form>
</div>
@endsection
