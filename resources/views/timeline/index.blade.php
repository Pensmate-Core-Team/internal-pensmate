@extends('layouts.app')
@section('title', 'Timeline konten')
@section('section', 'Timeline konten')
@section('content')
<div class="page-heading">
    <div><h1>Timeline konten</h1><p>Jadwal publikasi dan referensi sumber dalam satu tempat.</p></div>
    <a class="button button-primary" href="{{ route('contents.create') }}"><x-icon name="plus"/>Buat draf</a>
</div>
<x-timeline-tabs current="calendar" :carry="$filters->carry()"/>
<x-timeline-filter-form :action="route('timeline.index')" :filters="$filters" :divisions="$divisions" :assignees="$assignees">
    <div class="field"><label for="timeline-month">Bulan</label><input id="timeline-month" name="month" type="month" value="{{ $month }}" required></div>
</x-timeline-filter-form>
<div class="timeline-heading">
    <div><h2 id="timeline-month-heading">{{ $monthLabel }}</h2><p class="hint mt-1">{{ $contents->count() }} konten terjadwal &middot; Waktu Indonesia Barat</p></div>
    <nav class="timeline-navigation" aria-label="Navigasi bulan">
        <a class="icon-button" href="{{ $previousUrl }}" aria-label="Bulan sebelumnya"><x-icon name="back"/></a>
        <a class="button button-secondary" href="{{ $todayUrl }}">Bulan ini</a>
        <a class="icon-button" href="{{ $nextUrl }}" aria-label="Bulan berikutnya"><x-icon name="arrow"/></a>
    </nav>
</div>
@if($contents->isEmpty())
    <section class="panel empty-state"><x-icon name="calendar"/><h2>Belum ada konten terjadwal</h2><p>Tidak ada jadwal yang sesuai pada {{ $monthLabel }}. Coba ubah filter atau isi jadwal upload pada draf.</p><a class="button button-secondary" href="{{ route('contents.index') }}">Buka draf konten</a></section>
@else
<section class="timeline-calendar" aria-labelledby="timeline-month-heading">
    <div class="timeline-weekdays" aria-hidden="true">@foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)<span>{{ $weekday }}</span>@endforeach</div>
    <ol class="timeline-days">
        @foreach($days as $day)
        <li @class(['timeline-day', 'outside-month' => !$day['inMonth'], 'has-events' => $day['contents']->isNotEmpty(), 'is-today' => $day['isToday']]) data-date="{{ $day['date']->format('Y-m-d') }}">
            <h3 class="timeline-day-heading"><time datetime="{{ $day['date']->format('Y-m-d') }}" aria-label="{{ $day['date']->locale('id')->translatedFormat('l, j F Y') }}"><span>{{ $day['date']->day }}</span><span class="timeline-agenda-date"> {{ $day['date']->locale('id')->translatedFormat('F Y') }}</span></time>@if($day['isToday'])<span class="timeline-today-label">Hari ini</span>@endif</h3>
            <ol class="timeline-events">
                @foreach($day['contents'] as $content)
                <li><article id="timeline-content-{{ $content->id }}" class="timeline-event" data-timeline-content="{{ $content->id }}">
                    <div class="timeline-event-heading"><time datetime="{{ $content->scheduled_at->toIso8601String() }}" title="{{ $content->scheduled_at->format('H:i') }} WIB" aria-label="{{ $content->scheduled_at->format('H:i') }} WIB">{{ $content->scheduled_at->format('H:i') }}</time><span class="flag-group"><x-content-flags :content="$content" compact/><x-status :status="$content->status" compact/></span></div>
                    <h4><a href="{{ route('contents.show', $content) }}" title="{{ $content->title }}">{{ $content->title }}</a></h4>
                    <p class="timeline-event-meta" title="{{ $content->platform }} / {{ $content->assignee->name }}">{{ $content->platform }} &middot; {{ $content->assignee->name }}</p>
                    <x-timeline-references :content="$content"/>
                </article></li>
                @endforeach
            </ol>
        </li>
        @endforeach
    </ol>
</section>
@endif
<p class="hint mt-5">Hanya konten terjadwal yang boleh kamu akses ditampilkan. Draf tanpa jadwal tetap tersedia di Draf konten.</p>
@endsection
