@props(['current', 'carry' => [], 'range' => []])
{{-- from/to mean the same on list and board, so they travel between those two; the calendar uses month instead. --}}
<nav class="status-tabs timeline-tabs" aria-label="Tampilan timeline">
    @foreach(['calendar' => ['timeline.index', 'Kalender', []], 'list' => ['timeline.list', 'Daftar', $range], 'board' => ['timeline.board', 'Papan', $range]] as $key => [$route, $label, $extra])
    <a href="{{ route($route, [...$carry, ...$extra]) }}" @class(['selected' => $current === $key]) @if($current === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
