{{--
  Body of a free-text section (Teaching philosophy, Publications, custom sections …): the owner's Markdown, rendered
  safely (raw HTML escaped, no javascript: links). Inherits the template's typography. $key: the section key.
--}}
@php $textSection = $sections->firstWhere('key', $key); @endphp
@if ($textSection && filled($textSection->body))
  <div class="pf-text">{!! \App\Support\Markdown::article($textSection->body)['html'] !!}</div>
  @once
    <style>
      .pf-text { max-width: 70ch; line-height: 1.7; }
      .pf-text > :first-child { margin-top: 0; }
      .pf-text img { max-width: 100%; height: auto; border-radius: 8px; }
      .pf-text ul, .pf-text ol { padding-left: 1.3em; }
      .pf-text a { color: inherit; text-decoration: underline; text-underline-offset: 3px; }
      .pf-text blockquote { margin: 1em 0; padding-left: 1em; border-left: 3px solid color-mix(in srgb, currentColor 25%, transparent); }
    </style>
  @endonce
@endif
