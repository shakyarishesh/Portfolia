<div class="wrap contact">
  <p class="eyebrow reveal">{{ $section->eyebrow ?: 'Contact' }}</p>
  <h2 class="title big reveal">{{ $section->title }}</h2>
  @if ($profile->location || ($profile->show_phone && $profile->phone))
    <p class="lead reveal">
      @if ($profile->location)Based in {{ $profile->location }}@endif
      @if ($profile->show_phone && $profile->phone) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $profile->phone) }}">{{ $profile->phone }}</a>@endif
    </p>
  @endif
  <div class="cta reveal">
    @if ($profile->email)<a class="btn primary magnetic" href="mailto:{{ $profile->email }}">Email me</a>@endif
    @foreach ($links as $link)
      <a class="btn magnetic" href="{{ $link->url }}" target="_blank" rel="noopener me">{{ $link->label }}</a>
    @endforeach
    @if ($profile->resume)<a class="btn magnetic" href="{{ route('resume') }}">Download CV</a>@endif
  </div>

  @if ($profile->contact_form_enabled)
    <form class="contact-form reveal" method="post" action="{{ route('contact.store') }}" novalidate>
      @csrf
      @if (session('contact_sent'))
        <p class="form-ok" role="status">Thanks — your message was sent. I'll get back to you soon.</p>
      @endif
      <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="row">
        <label>Name<input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" @error('name') aria-invalid="true" @enderror></label>
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email" @error('email') aria-invalid="true" @enderror></label>
      </div>
      <label>Subject <small>(optional)</small><input name="subject" value="{{ old('subject') }}" maxlength="150"></label>
      <label>Message<textarea name="message" rows="5" required maxlength="5000" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea></label>
      @if ($errors->any())
        <ul class="form-errors" role="alert">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      @endif
      <button class="btn primary" type="submit" data-loading="Sending…">Send message</button>
    </form>
  @endif

  <footer class="reveal">© <span id="y">{{ now()->year }}</span> {{ $profile->full_name }}</footer>
</div>
