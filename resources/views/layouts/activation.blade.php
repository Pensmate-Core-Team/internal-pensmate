<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Aktivasi akun') | Pensmate</title>
    @include('components.font-links')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="activation-shell">
    <a class="brand" href="{{ route('login') }}"><span class="brand-mark">p<span>.</span></span>pensmate<span class="brand-internal">internal</span></a>
    <section class="panel activation-panel">
        @if($errors->any())<div class="notice notice-error" role="alert"><div><strong>Periksa kembali isianmu.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
        @yield('content')
    </section>
</main>
</body>
</html>
