@extends('layouts.app')
@section('title', 'Draf konten')
@section('content')
<div class="page-heading"><div><h1>Draf konten</h1><p>Siapkan materi, kumpulkan referensi, lalu ajukan untuk review.</p></div><a class="button button-primary" href="{{ route('contents.create') }}"><x-icon name="plus"/>Buat draf</a></div>
<div class="editorial-summary"><div><span class="summary-number">{{ $counts->get('ready', 0) }}</span><div><h2>Konten siap upload</h2><p>Materi yang sudah disetujui admin dan koordinator divisi lain.</p></div></div><a class="text-link" href="{{ route('contents.index', ['status' => 'ready']) }}">Lihat konten<x-icon name="arrow"/></a></div>
<nav class="status-tabs" aria-label="Filter status konten">
    <a href="{{ route('contents.index', request()->except('status', 'page')) }}" class="{{ !request('status') ? 'selected' : '' }}" @if(!request('status')) aria-current="page" @endif>Semua konten<span>{{ $counts->sum() }}</span></a>
    @foreach(\App\Models\Content::STATUSES as $key => $label)<a class="{{ request('status') === $key ? 'selected' : '' }}" @if(request('status') === $key) aria-current="page" @endif href="{{ route('contents.index', [...request()->except('page'), 'status' => $key]) }}">{{ $label }}<span>{{ $counts->get($key, 0) }}</span></a>@endforeach
</nav>
<form class="filter-bar" action="{{ route('contents.index') }}" method="GET">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="search-field"><label for="q" class="sr-only">Cari judul konten</label><x-icon name="search"/><input id="q" name="q" value="{{ request('q') }}" placeholder="Cari judul konten..."></div>
    <label for="platform" class="sr-only">Platform</label><select name="platform" id="platform"><option value="">Semua platform</option>@foreach(\App\Models\Content::PLATFORMS as $platform)<option @selected(request('platform') === $platform)>{{ $platform }}</option>@endforeach</select>
    <button class="button button-secondary">Terapkan filter</button>@if(request('q') || request('platform'))<a class="text-link" href="{{ route('contents.index', ['status' => request('status')]) }}">Reset filter</a>@endif
</form>
<div class="content-list">
    <div class="list-header"><span>Konten</span><span>Status</span><span>Penanggung jawab</span><span>Jadwal upload</span><span></span></div>
    @forelse($contents as $content)
    <article class="content-row">
        <div class="content-identity"><span class="content-file"><x-icon/></span><div><a class="content-title" href="{{ route('contents.show', $content) }}">{{ $content->title }}</a><p>{{ $content->platform }}<span class="meta-dot">·</span><a href="{{ route('contents.show', $content) }}#references" class="reference-count-link">{{ $content->references_count }} referensi</a></p></div></div>
        <div><x-status :status="$content->status"/></div>
        <div class="row-person"><span class="avatar avatar-small">{{ mb_substr($content->assignee->name, 0, 1) }}</span><span>{{ $content->assignee->name }}</span></div>
        <div class="row-date">@if($content->scheduled_at)<span>{{ $content->scheduled_at->locale('id')->translatedFormat('d M Y') }}</span><small>{{ $content->scheduled_at->format('H:i') }} WIB</small>@else<span class="muted">Belum dijadwalkan</span>@endif</div>
        <a class="icon-button" href="{{ route('contents.show', $content) }}" aria-label="Buka {{ $content->title }}"><x-icon name="arrow"/></a>
    </article>
    @empty
    <div class="empty-state"><x-icon/><h2>{{ request()->hasAny(['q', 'status', 'platform']) ? 'Konten tidak ditemukan' : 'Draf pertamamu dimulai di sini' }}</h2><p>{{ request()->hasAny(['q', 'status', 'platform']) ? 'Ubah pencarian atau filter untuk melihat konten lainnya.' : 'Tambahkan judul, caption, dan materi. Kamu bisa melengkapinya sebelum mengajukan review.' }}</p><a class="button button-secondary" href="{{ request()->hasAny(['q', 'status', 'platform']) ? route('contents.index') : route('contents.create') }}">{{ request()->hasAny(['q', 'status', 'platform']) ? 'Lihat semua konten' : 'Buat draf pertama' }}</a></div>
    @endforelse
</div>
<div class="pagination-row"><p class="muted">{{ $contents->total() }} konten{{ auth()->user()->isAdmin() ? ' dalam ruang kerja' : ' yang bisa kamu akses' }}</p>{{ $contents->links() }}</div>
@endsection
