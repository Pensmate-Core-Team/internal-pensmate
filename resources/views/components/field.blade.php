@props(['name', 'label', 'value' => '', 'type' => 'text', 'hint' => null])
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if(!in_array($type, ['file', 'password'])) value="{{ is_scalar(old($name, $value)) ? old($name, $value) : '' }}" @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror {{ $attributes }}>
    @if($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p class="field-error" id="{{ $name }}-error">{{ $message }}</p>@enderror
</div>
