@extends('layouts.app')
@section('title', $content->title)
@section('content')
<a class="back-link" href="{{ route('contents.index') }}"><x-icon name="back"/>Semua konten</a>
<div class="page-heading"><div><h1>{{ $content->title }}</h1><p>{{ $content->platform }}<span class="meta-dot">·</span>Dibuat oleh {{ $content->creator->name }}</p></div>@can('update', $content)<a class="button button-secondary" href="{{ route('contents.edit', $content) }}"><x-icon name="edit"/>Edit draf</a>@endcan</div>
<div class="detail-grid">
<div class="form-stack">
    <section class="panel"><div class="section-heading"><h2>Materi konten</h2><x-status :status="$content->status"/></div>
        @if($content->media_path)<div class="media-preview">@if(str_starts_with($content->media_mime, 'image/'))<img src="{{ route('contents.media', $content) }}" alt="Media untuk {{ $content->title }}">@else<video controls preload="metadata" aria-label="Media untuk {{ $content->title }}"><source src="{{ route('contents.media', $content) }}" type="{{ $content->media_mime }}"></video>@endif</div><a class="text-link media-link" href="{{ route('contents.media', $content) }}" target="_blank" rel="noopener">Buka file media<x-icon name="arrow"/></a>@else<div class="media-empty"><x-icon name="upload"/><p>File media belum ditambahkan.</p>@can('update', $content)<a class="text-link" href="{{ route('contents.edit', $content) }}">Tambahkan media</a>@endcan</div>@endif
        <h3 class="mt-6 mb-3">Caption</h3><div class="caption-text">{{ $content->caption ?: 'Caption belum ditulis.' }}</div>
    </section>
    <section class="panel" id="references"><div class="section-heading"><div><h2>Referensi konten</h2><p class="muted">Inspirasi dan sumber materi untuk draf ini.</p></div><span class="count-chip">{{ $content->references->count() }}</span></div>
        @forelse($content->references as $reference)
        <article class="reference-item"><div class="flex items-start gap-3"><span class="reference-icon"><x-icon name="link"/></span><div class="min-w-0 flex-1"><a class="reference-title" href="{{ $reference->url }}" target="_blank" rel="noopener noreferrer">{{ $reference->title }}<x-icon name="arrow"/></a><p class="reference-url">{{ parse_url($reference->url, PHP_URL_HOST) }}</p>@if($reference->notes)<p class="reference-notes">{{ $reference->notes }}</p>@endif<p class="hint mt-2">Ditambahkan oleh {{ $reference->creator->name }}</p></div></div>
            @if(auth()->user()->can('update', $content) && (auth()->user()->isAdmin() || $reference->created_by === auth()->id()))
            <details class="reference-edit" id="edit-reference-{{ $reference->id }}" @if($errors->getBag('reference-'.$reference->id)->any()) open @endif><summary>Edit referensi</summary><x-reference-form :action="route('references.update', [$content, $reference])" :bag="'reference-'.$reference->id" :reference="$reference" method="PUT"/><form class="mt-3" method="POST" action="{{ route('references.destroy', [$content, $reference]) }}" data-confirm="Hapus referensi ini?">@csrf @method('DELETE')<button class="text-link danger">Hapus referensi</button></form></details>
            @endif
        </article>
        @empty<p class="muted py-6">Belum ada referensi. Tambahkan sumber yang membantu tim memahami arah konten.</p>@endforelse
        @can('update', $content)<details class="add-reference" @if($errors->getBag('newReference')->any()) open @endif><summary><x-icon name="plus"/>Tambah referensi</summary><x-reference-form :action="route('references.store', $content)" bag="newReference"/></details>@endcan
    </section>
</div>
<aside class="form-stack">
    <section class="panel"><h2 class="mb-6">Detail publikasi</h2><dl class="detail-facts"><div><dt>Penanggung jawab</dt><dd><span class="avatar avatar-small">{{ mb_substr($content->assignee->name, 0, 1) }}</span>{{ $content->assignee->name }}</dd></div><div><dt>Platform</dt><dd>{{ $content->platform }}</dd></div><div><dt>Jadwal upload</dt><dd>{{ $content->scheduled_at?->locale('id')->translatedFormat('d M Y, H:i') ?? 'Belum dijadwalkan' }}{{ $content->scheduled_at ? ' WIB' : '' }}</dd></div><div><dt>Terakhir diperbarui</dt><dd>{{ $content->updated_at->locale('id')->diffForHumans() }}</dd></div></dl></section>
    @if($content->review_notes)<section class="review-note"><h2>Catatan review</h2><p>{{ $content->review_notes }}</p><span class="hint">{{ $content->reviewer?->name }}{{ $content->reviewed_at ? ' · '.$content->reviewed_at->format('d M Y') : '' }}</span></section>@endif
    <section class="panel"><h2 class="mb-3">Tahap berikutnya</h2>
        @can('update', $content)<p class="muted mb-6">Pastikan caption, media, dan jadwal sudah lengkap sebelum meminta review admin.</p><form method="POST" action="{{ route('contents.transition', $content) }}">@csrf<input type="hidden" name="action" value="submit"><button class="button button-primary w-full" data-loading="Mengajukan...">Ajukan review<x-icon name="arrow"/></button></form>@endcan
        @if($content->status === 'in_review')<p class="muted mb-6">Konten menunggu keputusan admin. Materi dikunci selama review.</p>@endif
        @can('review', $content)<form method="POST" class="form-stack" action="{{ route('contents.transition', $content) }}">@csrf<div class="field"><label for="review_notes">Catatan untuk pembuat konten</label><textarea name="review_notes" id="review_notes" rows="4" maxlength="5000">{{ old('review_notes') }}</textarea><p class="hint">Wajib diisi jika meminta revisi.</p></div><button class="button button-primary" name="action" value="approve">Setujui konten<x-icon name="check"/></button><button class="button button-secondary" name="action" value="revise">Minta revisi</button></form>@endcan
        @can('publish', $content)<p class="muted mb-6">Konten disetujui. Upload manual ke {{ $content->platform }}, lalu simpan link postingan.</p><form method="POST" class="form-stack" action="{{ route('contents.transition', $content) }}">@csrf<input type="hidden" name="action" value="publish"><x-field name="published_url" label="Link postingan" type="url" required placeholder="https://" maxlength="2000"/><button class="button button-primary" data-loading="Menyimpan...">Tandai terunggah<x-icon name="check"/></button></form>@endcan
        @if($content->status === 'published')<p class="muted mb-4">Tercatat terunggah pada {{ $content->published_at->locale('id')->translatedFormat('d M Y, H:i') }} WIB.</p><a class="button button-secondary w-full" href="{{ $content->published_url }}" target="_blank" rel="noopener noreferrer">Buka postingan<x-icon name="arrow"/></a>@endif
    </section>
</aside>
</div>
@endsection
