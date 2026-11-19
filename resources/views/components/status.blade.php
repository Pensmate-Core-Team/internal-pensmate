@props(['status', 'compact' => false])
@php
    $label = \App\Models\Content::STATUSES[$status] ?? $status;
    $shortLabel = ['draft' => 'Draf', 'in_review' => 'Review', 'revision' => 'Revisi', 'ready' => 'Siap', 'published' => 'Terbit'][$status] ?? $label;
@endphp
<span class="status status-{{ $status }}" @if($compact) title="{{ $label }}" aria-label="{{ $label }}" @endif><span class="status-dot" aria-hidden="true"></span>{{ $compact ? $shortLabel : $label }}</span>
