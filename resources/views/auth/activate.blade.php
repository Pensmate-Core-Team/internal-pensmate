@extends('layouts.activation')
@section('title', 'Aktifkan akun')
@section('content')
<h1>Aktifkan akunmu</h1>
<p class="muted mt-3">Akses ini untuk <strong>{{ $member->name }}</strong>{{ $member->generation ? ', Gen '.$member->generation : '' }}.</p>
<p class="hint mt-2">Berlaku sampai {{ $activation->expires_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB. Link hanya bisa dipakai sekali.</p>
@auth
    <div class="notice notice-error mt-6"><p>Kamu sedang masuk ke akun lain. Buka link ini di jendela privat, atau keluar dari akun tersebut terlebih dahulu.</p></div>
    <a class="button button-secondary mt-4" href="{{ route('contents.index') }}">Kembali ke akun saya</a>
@else
    <form method="POST" action="{{ route('activation.store', ['token' => $token]) }}" class="form-stack mt-7">
        @csrf
        <x-field name="email" label="Email untuk login" type="email" autocomplete="username" required maxlength="255" autofocus/>
        <x-field name="password" label="Password baru" type="password" autocomplete="new-password" required minlength="10" hint="Minimal 10 karakter, mengandung huruf dan angka. Password dibuat olehmu sendiri."/>
        <x-field name="password_confirmation" label="Konfirmasi password" type="password" autocomplete="new-password" required minlength="10"/>
        <button class="button button-primary w-full" data-loading="Mengaktifkan akun...">Aktifkan akun</button>
    </form>
    <p class="hint mt-5">Nama, generasi, dan peran mengikuti data yang sudah disiapkan admin.</p>
@endauth
@endsection
