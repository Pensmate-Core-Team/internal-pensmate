@props(['action', 'bag', 'reference' => null, 'method' => 'POST'])
@php
    $formErrors = $errors->getBag($bag);
    $retain = $formErrors->any();
@endphp
<form class="form-stack mt-4" method="POST" action="{{ $action }}">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    @foreach(['title' => 'Judul referensi', 'url' => 'URL referensi', 'notes' => 'Catatan referensi'] as $field => $label)
    <div class="field">
        <label for="{{ $bag }}-{{ $field }}">{{ $label }}</label>
        @if($field === 'notes')
            <textarea id="{{ $bag }}-{{ $field }}" name="notes" rows="3" maxlength="3000" @if($formErrors->has($field)) aria-invalid="true" aria-describedby="{{ $bag }}-{{ $field }}-error" @endif>{{ $retain ? old($field) : $reference?->$field }}</textarea>
        @else
            <input id="{{ $bag }}-{{ $field }}" name="{{ $field }}" type="{{ $field === 'url' ? 'url' : 'text' }}" value="{{ $retain ? old($field) : $reference?->$field }}" required maxlength="{{ $field === 'url' ? 2000 : 180 }}" @if($formErrors->has($field)) aria-invalid="true" aria-describedby="{{ $bag }}-{{ $field }}-error" @endif>
        @endif
        @if($formErrors->has($field))<p class="field-error" id="{{ $bag }}-{{ $field }}-error">{{ $formErrors->first($field) }}</p>@endif
    </div>
    @endforeach
    <button class="button button-secondary">Simpan referensi</button>
</form>
