@extends('layouts.activation')
@section('title', 'Link tidak berlaku')
@section('content')
<h1>Link tidak berlaku</h1>
<p class="muted mt-3">Link aktivasi kedaluwarsa, sudah digunakan, dicabut, atau akun belum dapat diaktifkan. Minta admin membuat link baru.</p>
<a class="button button-secondary mt-7" href="{{ route('login') }}">Ke halaman masuk</a>
@endsection
