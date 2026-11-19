@extends('layouts.app')
@section('title', $content->exists ? 'Edit draf' : 'Buat draf')
@section('content')
<a class="back-link" href="{{ $content->exists ? route('contents.show', $content) : route('contents.index') }}"><x-icon name="back"/>{{ $content->exists ? 'Kembali ke detail' : 'Semua konten' }}</a>
<div class="page-heading"><div><h1>{{ $content->exists ? 'Edit draf konten' : 'Buat draf konten' }}</h1><p>Simpan ide sekarang. Lengkapi materi sebelum mengajukan review.</p></div><span class="status status-draft">{{ $content->exists ? \App\Models\Content::STATUSES[$content->status] : 'Draf baru' }}</span></div>
<form method="POST" enctype="multipart/form-data" action="{{ $content->exists ? route('contents.update', $content) : route('contents.store') }}" class="detail-grid">@csrf @if($content->exists)@method('PUT')@endif
    <section class="panel form-stack"><div class="panel-heading"><h2>Materi konten</h2><p>Judul dan caption untuk publikasi.</p></div>
        <x-field name="title" label="Judul konten" :value="$content->title" required maxlength="180" autofocus/>
        <div class="field"><label for="caption">Caption</label><textarea id="caption" name="caption" rows="10" maxlength="15000" @error('caption') aria-invalid="true" @enderror>{{ old('caption', $content->caption) }}</textarea>@error('caption')<p class="field-error">{{ $message }}</p>@enderror<p class="hint">Caption dan media wajib lengkap saat diajukan untuk review.</p></div>
        <div class="upload-field"><x-icon name="upload"/>
            <label for="media_files">Media / carousel</label><input id="media_files" type="file" name="media_files[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" data-media-files data-file-list="selected-media-list" aria-describedby="media-files-hint">
            <p id="media-files-hint" class="hint mt-2">Maksimal 10 file per konten; setiap file maksimal 50 MB, mengikuti batas server. File baru ditambahkan setelah media yang tersimpan.</p>
            <ul id="selected-media-list" class="selected-files" aria-live="polite"></ul>
            @error('media_files')<p class="field-error">{{ $message }}</p>@enderror
            @foreach($errors->get('media_files.*') as $messages)@foreach($messages as $message)<p class="field-error">{{ $message }}</p>@endforeach
            @endforeach
            @error('remove_assets')<p class="field-error">{{ $message }}</p>@enderror
            @error('asset_order')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        @if($content->exists && $content->assets->isNotEmpty())
            <div><h3>Urutan media tersimpan</h3><p class="hint mt-2">Gunakan tombol naik/turun, lalu simpan draf. Centang Hapus untuk mengeluarkan media.</p></div>
            <ol class="asset-order-list" data-asset-order>
            @foreach($content->assets as $asset)<li data-asset-item>
                <input type="hidden" name="asset_order[]" value="{{ $asset->id }}">
                <a href="{{ route('contents.assets.show', [$content, $asset]) }}" class="asset-order-name text-link" target="_blank" rel="noopener">{{ $asset->original_name }}</a>
                <div class="asset-order-actions"><button class="button button-secondary" type="button" data-move-asset="up" aria-label="Naikkan {{ $asset->original_name }}">Naik</button><button class="button button-secondary" type="button" data-move-asset="down" aria-label="Turunkan {{ $asset->original_name }}">Turun</button><label class="asset-remove"><input type="checkbox" name="remove_assets[]" value="{{ $asset->id }}"> Hapus</label></div>
            </li>@endforeach
            </ol><p class="hint" data-order-status role="status"></p>
        @endif
    </section>
    <aside class="form-stack"><section class="panel form-stack"><div class="panel-heading"><h2>Rencana publikasi</h2></div>
        <div class="field"><label for="platform">Platform</label><select name="platform" id="platform" required>@foreach(\App\Models\Content::PLATFORMS as $platform)<option @selected(old('platform', $content->platform) === $platform)>{{ $platform }}</option>@endforeach</select>@error('platform')<p class="field-error">{{ $message }}</p>@enderror</div>
        @if(auth()->user()->isAdmin())<x-field name="division" label="Divisi konten" :value="$content->division" maxlength="120" hint="Wajib diisi sebelum review. Koordinator reviewer harus berasal dari divisi lain."/>@else<div><p class="field-label">Divisi konten</p><p>{{ $content->division ?: 'Belum diatur' }}</p><p class="hint">Divisi mengikuti penugasan admin pada profil anggota. Hubungi admin jika belum diisi.</p></div>@endif
        <x-field name="scheduled_at" label="Jadwal upload (WIB)" type="datetime-local" :value="$content->scheduled_at?->format('Y-m-d\TH:i')"/>
        @if(auth()->user()->isAdmin())<div class="field"><label for="assigned_to">Penanggung jawab</label><select name="assigned_to" id="assigned_to" required>@foreach($members as $member)<option value="{{ $member->id }}" @selected((string)old('assigned_to', $content->assigned_to) === (string)$member->id)>{{ $member->name }}{{ !$member->is_active ? ' (nonaktif)' : '' }}</option>@endforeach</select>@error('assigned_to')<p class="field-error">{{ $message }}</p>@enderror</div>@else<input type="hidden" name="assigned_to" value="{{ $content->assigned_to }}"><div><p class="field-label">Penanggung jawab</p><p>{{ $content->exists ? $content->assignee->name : auth()->user()->name }}</p></div>@endif
        <button class="button button-primary w-full" data-loading="Menyimpan draf...">Simpan draf<x-icon name="check"/></button>
    </section><div class="editor-note"><h3>Referensi menyusul</h3><p>Tambahkan link inspirasi dan sumber materi di halaman detail setelah draf tersimpan.</p></div></aside>
</form>
@endsection
