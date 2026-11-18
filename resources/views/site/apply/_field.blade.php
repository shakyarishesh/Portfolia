{{-- One input. $field (Resource-style definition), $name (HTML name), $id, $errorKey, $value; optional $required. --}}
@php
    $required ??= false;
    $error = $errors->first($errorKey);
    $describedBy = trim((isset($field['help']) ? "{$id}-help " : '').($error ? "{$id}-error" : ''));
    $wide = in_array($field['type'], ['textarea', 'lines', 'checkbox'], true);
    $value = is_array($value) ? implode($field['type'] === 'lines' ? "\n" : ', ', $value) : $value;
@endphp
<div class="field {{ $wide ? 'wide' : '' }}">
  @if ($field['type'] === 'checkbox')
    <label class="check"><input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" @checked($value) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif> {{ $field['label'] }}</label>
  @else
    <label for="{{ $id }}">{{ $field['label'] }}@if ($required) <span aria-hidden="true">*</span>@endif</label>
    @switch($field['type'])
      @case('textarea')
      @case('lines')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $field['rows'] ?? 4 }}" @required($required) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif>{{ $value }}</textarea>
        @break
      @case('select')
        <select id="{{ $id }}" name="{{ $name }}" @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif>
          @foreach ($field['options'] as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
          @endforeach
        </select>
        @break
      @default
        <input type="{{ in_array($field['type'], ['url', 'email', 'date', 'number'], true) ? $field['type'] : 'text' }}" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @required($required) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif>
    @endswitch
  @endif
  @isset($present){{-- End date's "Present" switch (the row's is_current); apply.js disables the date while it's ticked. --}}
    <label class="check present"><input type="checkbox" id="{{ $present['id'] }}" name="{{ $present['name'] }}" value="1" data-present @checked($present['checked'])> Present <span class="sr-only">— {{ $present['label'] }}</span></label>
  @endisset
  @isset($field['help'])<p class="help" id="{{ $id }}-help">{{ $field['help'] }}</p>@endisset
  @if ($error)<p class="error" id="{{ $id }}-error">{{ $error }}</p>@endif
</div>
