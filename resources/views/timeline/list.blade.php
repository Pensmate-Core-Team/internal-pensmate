@extends('layouts.app')
@section('title', 'Daftar timeline')
@section('section', 'Timeline konten')
@section('content')
<div class="page-heading">
    <div><h1>Timeline konten</h1><p>Semua rencana konten dalam satu daftar, lengkap dengan status, PIC, dan tenggat.</p></div>
    <a class="button button-primary" href="{{ route('contents.create') }}"><x-icon name="plus"/>Buat draf</a>
</div>
<x-timeline-tabs current="list" :carry="$filters->carry()"/>
<x-timeline-filter-form :action="route('timeline.list')" :filters="$filters" :divisions="$divisions" :assignees="$assignees">
    <div class="field"><label for="timeline-from">Dari tanggal</label><input id="timeline-from" name="from" type="date" value="{{ $filters->get('from') }}"></div>
    <div class="field"><label for="timeline-to">Sampai tanggal</label><input id="timeline-to" name="to" type="date" value="{{ $filters->get('to') }}"></div>
    <div class="field"><label for="timeline-sort">Urutkan</label><select id="timeline-sort" name="sort">@foreach(\App\Http\Controllers\TimelineListController::SORTS as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach</select></div>
</x-timeline-filter-form>
<ul class="timeline-summary" aria-label="Ringkasan konten">
    <li><span class="summary-count">{{ $summary['total'] }}</span> konten</li>
    <li><a href="{{ route('timeline.list', [...$filters->carry(), 'flag' => 'overdue']) }}"><span class="summary-count">{{ $summary['overdue'] }}</span> lewat tenggat</a></li>
    <li><a href="{{ route('timeline.list', [...$filters->carry(), 'flag' => 'needs_assignee']) }}"><span class="summary-count">{{ $summary['needs_assignee'] }}</span> butuh PIC</a></li>
</ul>
<div class="content-list">
    <div class="list-header"><span>Konten</span><span>Status</span><span>Penanggung jawab</span><span>Jadwal upload</span><span></span></div>
    @forelse($contents as $content)
    <article class="content-row">
        <div class="content-identity"><span class="content-file"><x-icon/></span><div><a class="content-title" href="{{ route('contents.show', $content) }}">{{ $content->title }}</a><p>{{ $content->platform }}<span class="meta-dot">·</span>{{ $content->division ?: 'Divisi belum diisi' }}<span class="meta-dot">·</span>{{ $content->references_count }} referensi</p>@if($content->isOverdue() || $content->needsAssignee())<p class="flag-group mt-2"><x-content-flags :content="$content"/></p>@endif</div></div>
        <div><x-status :status="$content->status"/></div>
        <div class="row-person"><span class="avatar avatar-small">{{ mb_substr($content->assignee->name, 0, 1) }}</span><span>{{ $content->assignee->name }}</span></div>
        <div class="row-date">@if($content->scheduled_at)<span>{{ $content->scheduled_at->locale('id')->translatedFormat('d M Y') }}</span><small>{{ $content->scheduled_at->format('H:i') }} WIB</small>@else<span class="muted">Belum dijadwalkan</span>@endif</div>
        <a class="icon-button" href="{{ route('contents.show', $content) }}" aria-label="Buka {{ $content->title }}"><x-icon name="arrow"/></a>
    </article>
    @empty
    <div class="empty-state"><x-icon name="calendar"/>
        @if($hasAnyContent)<h2>Tidak ada konten yang cocok</h2><p>Ubah atau reset filter untuk melihat konten lainnya.</p><a class="button button-secondary" href="{{ route('timeline.list') }}">Reset filter</a>
        @else<h2>Belum ada konten</h2><p>Konten yang kamu buat atau yang ditugaskan kepadamu akan muncul di sini.</p><a class="button button-secondary" href="{{ route('contents.create') }}">Buat draf</a>@endif
    </div>
    @endforelse
</div>
<div class="pagination-row"><p class="muted">{{ $contents->total() }} konten</p>{{ $contents->links() }}</div>
@endsection
