@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
<section class="login-story">
    <a class="brand" href="{{ route('login') }}"><span class="brand-mark">p<span>.</span></span>pensmate<span class="brand-internal">internal</span></a>
    <div><h1>Konten yang baik <br>dimulai dari tim <br>yang terhubung.</h1><p>Satu ruang untuk menyiapkan draf, merapikan referensi, dan membawa konten ke tahap siap upload.</p></div>
    <span class="login-foot">Ruang kerja tim Pensmate</span>
</section>
<section class="login-form-wrap"><div class="login-form">
    <h2>Masuk ke ruang kerja</h2><p class="muted mb-8">Gunakan akun yang diberikan admin tim.</p>
    <form method="POST" action="{{ route('login.store') }}" class="form-stack">@csrf
        <x-field name="email" label="Email" type="email" autocomplete="username" required autofocus/>
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required/>
        <button class="button button-primary w-full" data-loading="Memeriksa akun...">Masuk ke Pensmate<x-icon name="arrow"/></button>
    </form>
    <p class="hint mt-6">Belum punya akses atau lupa password? Hubungi admin tim.</p>
</div></section>
@endsection
