{{-- Shown on every template when the page differs from what the public sees (unpublished portfolio or unpublished template), or on the session-only demo ($demo). Self-styled so it works in any template. --}}
@if (! empty($demo))
  @php($btn = 'font:inherit;padding:.15rem .6rem;border:2px solid #1c1917;background:#fff;color:#1c1917;border-radius:4px;cursor:pointer;text-decoration:none')
  <div data-preview-bar role="region" aria-label="Demo controls" style="position:relative;z-index:2147483000;display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;justify-content:center;padding:.5rem 1rem;background:#c7d2fe;color:#1c1917;font:600 14px/1.4 system-ui,sans-serif;text-align:center;border-bottom:2px solid #1c1917">
    <span>DEMO — nothing is saved; your details stay only in this browser session.</span>
    <form method="get" action="{{ route('demo.show') }}" style="margin:0;display:flex;gap:.35rem;align-items:center">
      <label for="demo-template">Template</label>
      {{-- autocomplete=off: stop Firefox restoring a stale choice on reload; switch as soon as one is picked (button stays for no-JS). --}}
      <select id="demo-template" name="template" autocomplete="off" onchange="this.form.submit()" style="font:inherit;padding:.1rem .3rem;border:2px solid #1c1917;border-radius:4px;background:#fff;color:#1c1917">
        @foreach (\App\Support\Templates::available() as $key => $t)
          <option value="{{ $key }}" @selected($key === $template)>{{ $t['name'] }}</option>
        @endforeach
      </select>
      <button type="submit" style="{{ $btn }}">Switch</button>
    </form>
    <a href="{{ route('root') }}#demo" style="{{ $btn }}">Edit details</a>
    <form method="post" action="{{ route('demo.destroy') }}" style="margin:0">@csrf @method('DELETE')<button type="submit" style="{{ $btn }}">Clear</button></form>
    <a href="{{ route('apply.create') }}" style="{{ $btn }};background:#1c1917;color:#fff">Create your portfolio</a>
  </div>
@elseif (! empty($previewing) && isset($portfolio))
  <div data-preview-bar role="status" style="position:relative;z-index:2147483000;display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;justify-content:center;padding:.5rem 1rem;background:#fde047;color:#1c1917;font:600 14px/1.4 system-ui,sans-serif;text-align:center;border-bottom:2px solid #1c1917">
    <span>PREVIEW — not the live site.
      Template: <b>{{ \App\Support\Templates::meta($template)['name'] }}</b>@if ($template !== $portfolio->template) (live: {{ \App\Support\Templates::meta($portfolio->template)['name'] }})@endif ·
      Status: <b>{{ \App\Models\Portfolio::STATUSES[$portfolio->status] ?? $portfolio->status }}</b></span>
    @auth
      <form method="post" action="{{ route('admin.portfolios.preview.exit', $portfolio) }}" style="margin:0">@csrf<button type="submit" style="font:inherit;padding:.15rem .6rem;border:2px solid #1c1917;background:#fff;color:#1c1917;border-radius:4px;cursor:pointer">Exit preview</button></form>
    @endauth
  </div>
@endif
@if (! empty($demo) || (! empty($previewing) && isset($portfolio)))
  {{-- In flow (not sticky) so templates' own sticky headers aren't covered. Fixed-layout templates (classic) offset themselves by --preview-bar-h. --}}
  <script>(() => { const b = document.querySelector('[data-preview-bar]'), set = () => document.documentElement.style.setProperty('--preview-bar-h', b.offsetHeight + 'px'); set(); new ResizeObserver(set).observe(b); })();</script>
@endif
