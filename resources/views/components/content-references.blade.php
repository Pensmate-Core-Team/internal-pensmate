@props(['content'])
@can('view', $content)
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
@endcan
