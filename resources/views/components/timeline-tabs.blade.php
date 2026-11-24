@props(['current', 'carry' => []])
<nav class="status-tabs timeline-tabs" aria-label="Tampilan timeline">
    @foreach(['calendar' => ['timeline.index', 'Kalender'], 'list' => ['timeline.list', 'Daftar'], 'board' => ['timeline.board', 'Papan']] as $key => [$route, $label])
    <a href="{{ route($route, $carry) }}" @class(['selected' => $current === $key]) @if($current === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
