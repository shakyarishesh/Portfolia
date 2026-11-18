<section class="sec contact" id="contact" aria-labelledby="h-contact">
  <header class="sec-head"><p class="eyebrow">{{ $section->eyebrow ?: 'Contact' }}</p><h2 id="h-contact">{{ $section->title ?: 'Get in touch' }}</h2></header>
  <div class="contact-grid">
    <div>
      @if ($profile->email)<p><a class="mega" href="mailto:{{ $profile->email }}">{{ $profile->email }}</a></p>@endif
      @if ($profile->location || ($profile->show_phone && $profile->phone))
        <p class="muted">
          @if ($profile->location)Based in {{ $profile->location }}@endif
          @if ($profile->show_phone && $profile->phone) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $profile->phone) }}">{{ $profile->phone }}</a>@endif
        </p>
      @endif
      @if ($links->isNotEmpty() || $profile->resume)
        <ul class="tags links" aria-label="Elsewhere">
          @foreach ($links as $link)<li><a href="{{ $link->url }}" target="_blank" rel="noopener me">{{ $link->label }}</a></li>@endforeach
          @if ($profile->resume)<li><a href="{{ route('resume') }}">Download CV</a></li>@endif
        </ul>
      @endif
    </div>
    @if ($profile->contact_form_enabled)
      <form class="form" method="post" action="{{ route('contact.store') }}">
        @csrf
        @if (session('contact_sent'))<p class="ok" role="status">Thanks — your message was sent. I'll get back to you soon.</p>@endif
        @if ($errors->any())
          <ul class="errors" role="alert">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
        <div class="hp" aria-hidden="true"><label for="c-website">Website</label><input id="c-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="row">
          <p><label for="c-name">Name</label><input id="c-name" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" @error('name') aria-invalid="true" @enderror></p>
          <p><label for="c-email">Email</label><input id="c-email" type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email" @error('email') aria-invalid="true" @enderror></p>
        </div>
        <p><label for="c-subject">Subject <small>(optional)</small></label><input id="c-subject" name="subject" value="{{ old('subject') }}" maxlength="150"></p>
        <p><label for="c-message">Message</label><textarea id="c-message" name="message" rows="5" required minlength="10" maxlength="5000" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea></p>
        <button class="btn primary" type="submit">Send message</button>
      </form>
    @endif
  </div>
</section>
