@props(['content'])
<details class="timeline-references">
    <summary><x-icon name="link"/><span>{{ $content->references->count() }} referensi</span></summary>
    <div class="timeline-reference-body">
        @if($content->references->isNotEmpty())
        <ul>@foreach($content->references as $reference)
            <li><a href="{{ $reference->url }}" target="_blank" rel="noopener noreferrer">{{ $reference->title }}<span class="sr-only"> (buka tab baru)</span></a>@if($reference->notes)<p>{{ $reference->notes }}</p>@endif</li>
        @endforeach</ul>
        @else<p>Belum ada referensi untuk konten ini.</p>@endif
        <a class="timeline-reference-detail" href="{{ route('contents.show', $content) }}#references">@can('update', $content){{ $content->references->isEmpty() ? 'Tambah referensi' : 'Kelola referensi' }}@else Buka detail referensi @endcan</a>
    </div>
</details>
