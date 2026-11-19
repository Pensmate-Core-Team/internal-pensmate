@extends('layouts.app')
@section('title', $member->name)
@section('section', 'Manage member')
@section('content')
<a class="back-link" href="{{ route('members.index') }}"><x-icon name="back"/>Semua member</a>
<div class="page-heading"><div><h1>{{ $member->name }}</h1><p>{{ $member->generation ? 'Member Gen '.$member->generation : 'Member Pensmate' }}</p></div><a class="button button-primary" href="{{ route('members.edit', $member) }}"><x-icon name="edit"/>Edit member</a></div>
<div class="detail-grid">
    <div class="form-stack"><section class="panel"><h2>Profil anggota</h2><dl class="member-detail-facts">
        <div><dt>Status member</dt><dd><span class="status {{ $member->is_active ? 'status-ready' : 'status-draft' }}"><span class="status-dot" aria-hidden="true"></span>{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span></dd></div>
        <div><dt>Generasi</dt><dd>{{ $member->generation ? 'Gen '.$member->generation : 'Belum diisi' }}</dd></div>
        <div><dt>Kelas</dt><dd>{{ $member->class_name ?: 'Belum diisi' }}</dd></div>
        <div><dt>Program studi</dt><dd>{{ $member->study_program ?: 'Belum diisi' }}</dd></div>
        <div><dt>Peran</dt><dd>{{ $member->roleLabel() }}</dd></div><div><dt>Divisi</dt><dd>{{ $member->division ?: 'Belum diisi' }}</dd></div>
        <div><dt>Email</dt><dd>{{ $member->email ?: 'Belum diisi' }}</dd></div>
        <div><dt>Akses login</dt><dd>{{ !$member->email ? 'Belum dibuat' : ($member->is_active ? 'Tersedia' : 'Dinonaktifkan') }}</dd></div>
        <div><dt>Konten ditugaskan</dt><dd>{{ $member->assigned_contents_count }} konten</dd></div>
    </dl></section>
    <x-member-activation :member="$member" :activation="$activation"/>
    </div>
    <aside class="editor-note"><h2>Hapus dari daftar</h2><p>Member akan diarsipkan dan akses login dinonaktifkan. Konten serta referensi yang pernah dibuat tetap tersimpan.</p>
        <form class="mt-6" method="POST" action="{{ route('members.destroy', $member) }}" data-confirm="Hapus member ini dari daftar? Histori konten tetap tersimpan.">@csrf @method('DELETE')<button class="button button-secondary danger w-full" data-loading="Menghapus...">Hapus member</button></form>
    </aside>
</div>
@endsection
