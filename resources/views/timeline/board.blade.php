@extends('layouts.app')
@section('title', 'Papan timeline')
@section('section', 'Timeline konten')
@section('content')
<div class="page-heading">
    <div><h1>Timeline konten</h1><p>Lihat posisi setiap konten di alur kerja. Ubah status dari halaman detail konten.</p></div>
    <a class="button button-primary" href="{{ route('contents.create') }}"><x-icon name="plus"/>Buat draf</a>
</div>
<x-timeline-tabs current="board" :carry="$filters->carry()"/>
<x-timeline-filter-form :action="route('timeline.board')" :filters="$filters" :divisions="$divisions" :assignees="$assignees" :show-status="false">
    <div class="field"><label for="timeline-from">Dari tanggal</label><input id="timeline-from" name="from" type="date" value="{{ $filters->get('from') }}"></div>
    <div class="field"><label for="timeline-to">Sampai tanggal</label><input id="timeline-to" name="to" type="date" value="{{ $filters->get('to') }}"></div>
</x-timeline-filter-form>
<div class="timeline-board">
    @foreach($columns as $column)
    <section class="board-column" aria-labelledby="board-{{ $column['status'] }}">
        <h2 id="board-{{ $column['status'] }}" class="board-column-heading"><x-status :status="$column['status']"/><span class="count-chip">{{ $column['total'] }}</span></h2>
        @if($column['items']->isEmpty())
            <p class="board-empty">Tidak ada konten.</p>
        @else
        <ol class="board-cards">
            @foreach($column['items'] as $content)
            <li><article class="board-card">
                <h3><a href="{{ route('contents.show', $content) }}">{{ $content->title }}</a></h3>
                <p class="board-card-date">@if($content->scheduled_at){{ $content->scheduled_at->locale('id')->translatedFormat('d M Y') }}, {{ $content->scheduled_at->format('H:i') }} WIB @else Belum dijadwalkan @endif</p>
                <p class="board-card-meta">{{ $content->platform }}<span class="meta-dot">·</span>{{ $content->assignee->name }}<span class="meta-dot">·</span>{{ $content->references_count }} referensi</p>
                @if($content->isOverdue() || $content->needsAssignee())<p class="flag-group mt-2"><x-content-flags :content="$content"/></p>@endif
            </article></li>
            @endforeach
        </ol>
        @endif
        @if($column['more'])<a class="text-link" href="{{ route('timeline.list', [...$filters->carry(), ...array_filter(['from' => $filters->get('from'), 'to' => $filters->get('to')]), 'status' => $column['status']]) }}">{{ $column['limitedToRecent'] ? 'Lihat semua konten terunggah di Daftar' : 'Lihat semua di Daftar' }}<x-icon name="arrow"/></a>@endif
        @if($column['limitedToRecent'])<p class="hint">Menampilkan yang terunggah 30 hari terakhir.</p>@endif
    </section>
    @endforeach
</div>
@endsection
