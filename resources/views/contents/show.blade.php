@extends('layouts.app')
@section('title', $content->title)
@section('content')
<a class="back-link" href="{{ route('contents.index') }}"><x-icon name="back"/>Semua konten</a>
@if($content->scheduled_at)<a class="back-link ml-5" href="{{ route('timeline.index', ['month' => $content->scheduled_at->format('Y-m')]) }}#timeline-content-{{ $content->id }}"><x-icon name="calendar"/>Lihat di timeline</a>@endif
<div class="page-heading"><div><h1>{{ $content->title }}</h1><p>{{ $content->platform }} ? {{ $content->division ?: 'Divisi belum diisi' }} ? Dibuat oleh {{ $content->creator->name }}</p></div>@can('update', $content)<a class="button button-secondary" href="{{ route('contents.edit', $content) }}"><x-icon name="edit"/>Edit draf</a>@endcan</div>
<div class="detail-grid">
<div class="form-stack">
    <section class="panel">
        <div class="section-heading"><h2>Materi konten</h2><span class="flag-group"><x-content-flags :content="$content"/><x-status :status="$content->status"/></span></div>
        @if($content->assets->isNotEmpty())
        <ol class="asset-gallery">
            @foreach($content->assets as $asset)
            <li class="asset-preview">
                <div class="media-preview">@if(str_starts_with($asset->mime_type, 'image/'))<img src="{{ route('contents.assets.show', [$content, $asset]) }}" alt="Media {{ $loop->iteration }} untuk {{ $content->title }}" loading="lazy">@else<video controls preload="metadata" aria-label="Media {{ $loop->iteration }} untuk {{ $content->title }}"><source src="{{ route('contents.assets.show', [$content, $asset]) }}" type="{{ $asset->mime_type }}"></video>@endif</div>
                <p class="asset-name">{{ $loop->iteration }}. {{ $asset->original_name }}</p>
                @can('download', $content)<a class="text-link" href="{{ route('contents.assets.download', [$content, $asset]) }}">Unduh media {{ $loop->iteration }}</a>@else<a class="text-link" href="{{ route('contents.assets.show', [$content, $asset]) }}" target="_blank" rel="noopener">Buka media {{ $loop->iteration }}</a>@endcan
            </li>
            @endforeach
        </ol>
        @elseif($content->media_path)
            <a class="text-link" href="{{ route('contents.media', $content) }}" target="_blank" rel="noopener">Buka file media</a>
        @else
            <div class="media-empty"><x-icon name="upload"/><p>File media belum ditambahkan.</p>@can('update', $content)<a class="text-link" href="{{ route('contents.edit', $content) }}">Tambahkan media</a>@endcan</div>
        @endif
        <div class="section-heading mt-7"><h3>Caption</h3>@if(filled($content->caption))<button class="button button-secondary" type="button" data-copy-target="content-caption-{{ $content->id }}" data-copy-status="caption-status-{{ $content->id }}">Salin caption</button>@endif</div>
        <div id="content-caption-{{ $content->id }}" class="caption-text">{{ $content->caption ?: 'Caption belum ditulis.' }}</div>
        <p id="caption-status-{{ $content->id }}" class="hint copy-status" role="status" aria-live="polite"></p>
    </section>
    <x-content-references :content="$content"/>
    <section class="panel"><h2>Riwayat review</h2><ol class="review-history">
        @forelse($content->reviews->sortByDesc('id') as $review)
        <li><p><strong>{{ $review->reviewer->name }}</strong> {{ $review->decision === 'approved' ? 'menyetujui' : 'meminta revisi' }} versi {{ $review->revision_number }}</p><p class="hint">{{ $review->reviewer_role === 'admin' ? 'Admin' : 'Koordinator' }}{{ $review->reviewer_division ? ' ? '.$review->reviewer_division : '' }} ? {{ $review->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>@if($review->comment)<p class="mt-2 whitespace-pre-wrap">{{ $review->comment }}</p>@endif</li>
        @empty<li class="muted text-sm">Belum ada keputusan review.</li>@endforelse
    </ol></section>
    <x-content-history :content="$content"/>
</div>
<aside class="form-stack">
    <section class="panel"><h2 class="mb-6">Detail publikasi</h2><dl class="detail-facts"><div><dt>Penanggung jawab</dt><dd>{{ $content->assignee->name }}</dd></div><div><dt>Divisi konten</dt><dd>{{ $content->division ?: 'Belum diisi' }}</dd></div><div><dt>Jadwal upload</dt><dd>{{ $content->scheduled_at?->locale('id')->translatedFormat('d M Y, H:i') ?? 'Belum dijadwalkan' }}{{ $content->scheduled_at ? ' WIB' : '' }}</dd></div><div><dt>Versi diajukan</dt><dd>{{ $content->revision_number ?: 'Belum diajukan' }}</dd></div></dl></section>
    <section class="panel form-stack">
        <h2>Persetujuan konten</h2>
        <p class="hint">Wajib disetujui oleh admin dan satu koordinator dari divisi lain, dengan dua reviewer berbeda.</p>
        <ul class="approval-checklist">@foreach(['admin' => 'Admin', 'coordinator' => 'Koordinator divisi lain'] as $role => $label)
            @php($approved = $content->reviews->where('revision_number', $content->revision_number)->where('reviewer_role', $role)->where('decision', 'approved')->isNotEmpty())
            <li><span>{{ $label }}</span><span class="status {{ $approved ? 'status-ready' : 'status-draft' }}">{{ $approved ? 'Disetujui' : 'Menunggu' }}</span></li>
        @endforeach</ul>
        @if($content->status === 'revision' && $content->review_notes)<div class="review-note"><h3>Catatan revisi</h3><p>{{ $content->review_notes }}</p></div>@endif
        @can('update', $content)<form method="POST" action="{{ route('contents.transition', $content) }}">@csrf<input type="hidden" name="action" value="submit"><button class="button button-primary w-full" data-loading="Mengajukan...">Ajukan review</button></form>@endcan
        @if($content->status === 'in_review')<p class="hint">Materi dikunci selama review. Persetujuan pertama belum membuka akses unduh final.</p>@endif
        @can('review', $content)<form method="POST" class="form-stack" action="{{ route('contents.transition', $content) }}">@csrf<div class="field"><label for="review_notes">Catatan review</label><textarea name="review_notes" id="review_notes" rows="4" maxlength="5000">{{ old('review_notes') }}</textarea><p class="hint">Wajib diisi saat meminta revisi.</p></div><button class="button button-primary" name="action" value="approve">Setujui versi ini<x-icon name="check"/></button><button class="button button-secondary" name="action" value="revise">Minta revisi</button></form>@endcan
        @can('publish', $content)<p class="hint">Unduh media, salin caption, lalu upload manual sesuai urutan. Simpan link postingan setelah terunggah.</p><form method="POST" class="form-stack" action="{{ route('contents.transition', $content) }}">@csrf<input type="hidden" name="action" value="publish"><x-field name="published_url" label="Link postingan" type="url" required placeholder="https://" maxlength="2000"/><button class="button button-primary" data-loading="Menyimpan...">Tandai terunggah</button></form>@endcan
        @if($content->status === 'published')<a class="button button-secondary" href="{{ $content->published_url }}" target="_blank" rel="noopener noreferrer">Buka postingan<x-icon name="arrow"/></a>@endif
    </section>
</aside>
</div>
@endsection
