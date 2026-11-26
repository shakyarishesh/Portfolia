{{-- One form field. $name, $field, $value; optional $prefix (e.g. "values") for nested inputs. --}}
@php
    $inputName = isset($prefix) ? "{$prefix}[{$name}]" : $name;
    $errorKey = isset($prefix) ? "{$prefix}.{$name}" : $name;
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $inputName);
    $old = old($errorKey, $value);
    $wide = in_array($field['type'], ['textarea', 'lines'], true) || ($field['wide'] ?? false);
    $describedBy = trim((isset($field['help']) ? "{$id}_help " : '').($errors->has($errorKey) ? "{$id}_err" : ''));
    $required = in_array('required', array_filter($field['rules'] ?? [], 'is_string'), true);
@endphp
<div class="field {{ $wide ? 'wide' : '' }}">
  @if ($field['type'] === 'checkboxes')
    <fieldset class="checks" @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
      <legend>{{ $field['label'] }}</legend>
      @foreach ($field['options'] as $optValue => $optLabel)
        <label class="check"><input type="checkbox" name="{{ $inputName }}[]" value="{{ $optValue }}" @checked(in_array((string) $optValue, array_map('strval', (array) $old), true))> {{ $optLabel }}</label>
      @endforeach
    </fieldset>
  @elseif ($field['type'] === 'checkbox')
    <label class="check">@unless (isset($prefix))<input type="hidden" name="{{ $inputName }}" value="0">@endunless<input type="checkbox" id="{{ $id }}" name="{{ $inputName }}" value="1" @checked($old) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif> {{ $field['label'] }}</label>
  @else
    <label for="{{ $id }}">{{ $field['label'] }}@if ($required) <span aria-hidden="true" class="muted">*</span>@endif</label>
    @switch($field['type'])
      @case('textarea')
      @case('lines')
        <textarea id="{{ $id }}" name="{{ $inputName }}" rows="{{ $field['rows'] ?? ($field['type'] === 'lines' ? 5 : 4) }}" @required($required) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @error($errorKey) aria-invalid="true" @enderror>{{ is_array($old) ? implode("\n", $old) : $old }}</textarea>
        @break
      @case('select')
        <select id="{{ $id }}" name="{{ $inputName }}" @required($required) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @error($errorKey) aria-invalid="true" @enderror>
          @if (! $required || isset($field['placeholder']))<option value="">{{ $field['placeholder'] ?? '—' }}</option>@endif
          @foreach ($field['options'] as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) $old === (string) $optValue)>{{ $optLabel }}</option>
          @endforeach
        </select>
        @break
      @default
        @php $htmlType = ['url' => 'url', 'email' => 'email', 'password' => 'password', 'date' => 'date', 'datetime' => 'datetime-local', 'number' => 'number'][$field['type']] ?? 'text'; @endphp
        <input type="{{ $htmlType }}" id="{{ $id }}" name="{{ $inputName }}" value="{{ is_array($old) ? implode(', ', $old) : $old }}" @required($required) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @error($errorKey) aria-invalid="true" @enderror>
    @endswitch
  @endif
  @isset($field['help'])<span class="help" id="{{ $id }}_help">{{ $field['help'] }}</span>@endisset
  @error($errorKey)<span class="error" id="{{ $id }}_err">{{ $message }}</span>@enderror
</div>
