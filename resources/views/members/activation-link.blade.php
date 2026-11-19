@extends('layouts.app')
@section('title', 'Link aktivasi member')
@section('section', 'Manage member')
@section('content')
<a class="back-link" href="{{ route('members.show', $member) }}"><x-icon name="back"/>Kembali ke detail member</a>
<div class="page-heading"><div><h1>Link aktivasi siap</h1><p>Bagikan secara pribadi kepada {{ $member->name }}.</p></div></div>
<section class="panel activation-result">
    <h2>Salin dan bagikan link</h2>
    <p class="hint mt-3">Link hanya ditampilkan pada halaman ini. Jika hilang, buat link baru untuk membatalkan link lama.</p>
    <div class="activation-url mt-5" id="member-activation-url">{{ $activationUrl }}</div>
    <div class="mt-5 flex flex-wrap gap-3"><button type="button" class="button button-primary" data-copy-target="member-activation-url" data-copy-status="activation-copy-status" data-copy-success="Link aktivasi berhasil disalin." data-copy-failure="Salin otomatis tidak tersedia. Pilih link lalu gunakan menu Salin atau Ctrl+C.">Salin link aktivasi</button><a class="button button-secondary" href="{{ $activationUrl }}" target="_blank" rel="noopener noreferrer">Buka link</a></div>
    <p class="hint copy-status" id="activation-copy-status" role="status" aria-live="polite"></p>
    <p class="hint mt-3">Kedaluwarsa: {{ $activation->expires_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB. Link sekali pakai; link sebelumnya sudah dibatalkan.</p>
    <p class="hint mt-3">Untuk mencoba sebagai member, buka di jendela privat agar tidak memakai sesi admin. Pastikan alamat aplikasi dapat diakses penerima.</p>
</section>
@endsection
