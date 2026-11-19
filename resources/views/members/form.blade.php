@extends('layouts.app')
@section('title', $member->exists ? 'Edit member' : 'Tambah member')
@section('section', 'Manage member')
@section('content')
<a class="back-link" href="{{ route('members.index') }}"><x-icon name="back"/>Semua member</a>
<div class="page-heading"><div><h1>{{ $member->exists ? 'Edit member' : 'Tambah member' }}</h1><p>Lengkapi profil anggota. Akses login dapat ditambahkan saat email sudah tersedia.</p></div></div>
<form method="POST" class="detail-grid" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}">
@csrf @if($member->exists)@method('PUT')@endif
<section class="panel form-stack">
    <h2>Data member</h2>
    <x-field name="name" label="Nama lengkap" :value="$member->name" required autocomplete="name" maxlength="120"/>
    <x-field name="generation" label="Generasi" type="number" :value="$member->generation" min="1" max="99" placeholder="Contoh: 1 atau 2"/>
    <x-field name="class_name" label="Kelas" :value="$member->class_name" maxlength="255" placeholder="Contoh: 2 D4 IT B"/>
    <x-field name="study_program" label="Program studi" :value="$member->study_program" maxlength="255" placeholder="Contoh: D4 Teknik Informatika"/>
    <div class="field"><label for="is_active">Status member</label><select id="is_active" name="is_active" required><option value="1" @selected((string)old('is_active', (int)$member->is_active) === '1')>Aktif</option><option value="0" @selected((string)old('is_active', (int)$member->is_active) === '0')>Nonaktif</option></select>@error('is_active')<p class="field-error">{{ $message }}</p>@enderror</div>
    <x-field name="division" label="Divisi" :value="$member->division" maxlength="120" hint="Wajib untuk koordinator. Gunakan nama divisi yang konsisten, misalnya sesuai struktur organisasi."/>
    <div class="field"><label for="role">Peran anggota</label><select name="role" id="role"><option value="member" @selected(old('role', $member->role) === 'member')>Member</option><option value="coordinator" @selected(old('role', $member->role) === 'coordinator')>Koordinator divisi</option></select>@error('role')<p class="field-error">{{ $message }}</p>@enderror<p class="hint">Koordinator dengan email dan akses aktif dapat mereview konten divisi lain. Admin tetap dikelola terpisah.</p></div>
    <div class="form-divider"></div>
    <div><h2>Akses login (opsional)</h2><p class="hint mt-2">Biarkan email dan password kosong untuk menyimpan data anggota tanpa akses login. Jika mengisi email pertama kali, isi password juga.</p></div>
    <x-field name="email" label="Email" type="email" :value="$member->email" autocomplete="email" maxlength="255"/>
    <x-field name="password" :label="$member->exists && $member->email ? 'Password baru' : 'Password'" type="password" autocomplete="new-password" hint="Minimal 10 karakter, mengandung huruf dan angka. Kosongkan untuk mempertahankan password yang sudah ada."/>
    <x-field name="password_confirmation" label="Konfirmasi password" type="password" autocomplete="new-password"/>
    <div class="flex gap-3 flex-wrap"><button class="button button-primary" data-loading="Menyimpan member...">Simpan member<x-icon name="check"/></button><a class="button button-secondary" href="{{ $member->exists ? route('members.show', $member) : route('members.index') }}">Batal</a></div>
</section>
<aside class="editor-note"><h2>Profil sesuai sumber</h2><p>Gen 1 mencatat kelas. Gen 2 mencatat program studi. Isi sesuai data yang tersedia.</p><p class="mt-4">Anggota yang belum memiliki email tetap tercatat, tetapi belum bisa login.</p></aside>
</form>
@endsection
