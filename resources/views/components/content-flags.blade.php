@props(['content', 'compact' => false])
@php($flags = array_filter([
    'overdue' => $content->isOverdue() ? ['Lewat tenggat', 'Telat', 'calendar'] : null,
    'needs-assignee' => $content->needsAssignee() ? ['Butuh PIC', 'PIC?', 'users'] : null,
]))
@foreach($flags as $key => [$label, $short, $icon])
<span class="flag flag-{{ $key }}" @if($compact) title="{{ $label }}" aria-label="{{ $label }}" @endif><x-icon :name="$icon"/>{{ $compact ? $short : $label }}</span>
@endforeach
