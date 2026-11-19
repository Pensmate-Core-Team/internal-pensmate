@extends('layouts.app')
@section('title', 'Manage member')
@section('section', 'Manage member')
@section('content')
<div class="page-heading">
    <div><h1>Member Pensmate</h1><p>{{ $activeCount }} member aktif dari {{ $total }} anggota. Kelola data Gen 1, Gen 2, dan anggota lainnya.</p></div>
    <a class="button button-primary" href="{{ route('members.create') }}"><x-icon name="plus"/>Tambah member</a>
</div>
<form class="filter-bar" method="GET" action="{{ route('members.index') }}">
    <div class="search-field"><label class="sr-only" for="q">Cari member</label><x-icon name="search"/><input id="q" name="q" value="{{ request('q') }}" maxlength="150" placeholder="Cari nama, kelas, atau prodi..."></div>
    <label for="generation" class="sr-only">Generasi</label>
    <select id="generation" name="generation"><option value="">Semua generasi</option>@foreach($generations as $generation)<option value="{{ $generation }}" @selected((string)request('generation') === (string)$generation)>Gen {{ $generation }}</option>@endforeach</select>
    <label for="active" class="sr-only">Status member</label><select id="active" name="active"><option value="">Semua status</option><option value="1" @selected(request('active') === '1')>Aktif</option><option value="0" @selected(request('active') === '0')>Nonaktif</option></select>
    <button class="button button-secondary">Terapkan filter</button>
    @if(request()->hasAny(['q','generation','active']))<a class="text-link" href="{{ route('members.index') }}">Reset filter</a>@endif
</form>
@if($members->isNotEmpty())
<div class="member-grid" aria-label="Daftar member Pensmate">
    @foreach($members as $member)
    <article class="member-card">
        <div class="member-card-heading"><span class="member-generation">{{ $member->generation ? 'Gen '.$member->generation : 'Generasi belum diisi' }}</span><span class="status {{ $member->is_active ? 'status-ready' : 'status-draft' }}"><span class="status-dot" aria-hidden="true"></span>{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
        <span class="avatar member-card-avatar" aria-hidden="true">{{ mb_substr($member->name, 0, 1) }}</span>
        <h2 class="member-card-name"><a href="{{ route('members.show', $member) }}">{{ $member->name }}</a></h2>
        <dl class="member-card-facts">
            @if($member->class_name)<div><dt>Kelas</dt><dd>{{ $member->class_name }}</dd></div>@endif
            @if($member->study_program)<div><dt>Program studi</dt><dd>{{ $member->study_program }}</dd></div>@endif
            @if(!$member->class_name && !$member->study_program)<div><dt>Profil</dt><dd>Kelas dan prodi belum diisi</dd></div>@endif
        </dl>
        <div class="member-card-footer"><a class="text-link" href="{{ route('members.show', $member) }}">Lihat detail<x-icon name="arrow"/></a><a class="icon-button" href="{{ route('members.edit', $member) }}" aria-label="Edit {{ $member->name }}" title="Edit member"><x-icon name="edit"/></a></div>
    </article>
    @endforeach
</div>
@else
<div class="panel empty-state"><x-icon name="users"/><h2>Member tidak ditemukan</h2><p>Ubah pencarian atau filter. Kamu juga bisa menambahkan member baru.</p><a class="button button-secondary" href="{{ route('members.create') }}">Tambah member</a></div>
@endif
<div class="pagination-row"><p class="muted">{{ $members->total() }} member ditemukan</p>{{ $members->links() }}</div>
@endsection
