<!DOCTYPE html>
<html lang="id" data-type="software">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Ruang kerja tim Pensmate untuk menyiapkan konten, menyimpan referensi, dan mengelola member.">
    <meta name="theme-color" content="#f8f7f4">
    <meta name="page-type" content="software"><meta name="site-kind" content="software">
    <title>@yield('title', 'Ruang kerja') · Pensmate</title>
    @include('components.font-links')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten</a>
@auth
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('contents.index') }}"><span class="brand-mark">p<span>.</span></span>pensmate<span class="brand-internal">internal</span></a>
        <button class="icon-button mobile-menu sidebar-close" data-menu-close type="button" aria-label="Tutup navigasi"><x-icon name="back"/></button>
        <div class="workspace-name"><span class="workspace-avatar">P</span><div><strong>Tim Pensmate</strong><span>Ruang kerja konten</span></div></div>
        <nav aria-label="Navigasi utama">
            <a class="nav-item {{ request()->routeIs('timeline.*') ? 'is-active' : '' }}" @if(request()->routeIs('timeline.*')) aria-current="page" @endif href="{{ route('timeline.index') }}"><x-icon name="calendar"/>Timeline konten</a>
            <a class="nav-item {{ request()->routeIs('contents.*') ? 'is-active' : '' }}" @if(request()->routeIs('contents.*')) aria-current="page" @endif href="{{ route('contents.index') }}"><x-icon name="grid"/>Draf konten</a>
            @can('manage-members')<a class="nav-item {{ request()->routeIs('members.*') ? 'is-active' : '' }}" @if(request()->routeIs('members.*')) aria-current="page" @endif href="{{ route('members.index') }}"><x-icon name="users"/>Manage member</a>@endcan
        </nav>
        <div class="sidebar-note"><x-icon name="link"/><p>Referensi tersimpan di setiap detail konten.</p></div>
        <div class="account"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->roleLabel() }}</span></div><form action="{{ route('logout') }}" method="POST">@csrf<button class="icon-button" aria-label="Keluar akun" title="Keluar akun"><x-icon name="logout"/></button></form></div>
    </aside>
    <div class="main-shell">
        <header class="topbar"><div class="flex items-center gap-3"><button class="icon-button mobile-menu" type="button" data-menu-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Buka navigasi"><x-icon name="menu"/></button><span>Pensmate <span class="breadcrumb-slash" aria-hidden="true">/</span> <strong>@yield('section', 'Draf konten')</strong></span></div><span class="topbar-date"><x-icon name="calendar"/>{{ now()->locale('id')->translatedFormat('d M Y') }}</span></header>
        <main id="main" class="workspace" tabindex="-1">
            @if(session('success'))<div class="notice notice-success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
            @php($allErrors = collect($errors->getBags())->flatMap(fn ($bag) => $bag->all()))
            @if($allErrors->isNotEmpty())<div class="notice notice-error" role="alert"><div><strong>Periksa kembali isianmu.</strong><ul>@foreach($allErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
            @yield('content')
            <footer class="workspace-footer"><span>Pensmate · Ruang kerja tim</span><span>Rencanakan. Siapkan. Publikasikan.</span></footer>
        </main>
    </div>
</div>
@else
<main id="main" class="login-shell" tabindex="-1">@yield('content')</main>
@endauth
</body>
</html>
