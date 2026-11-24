@props(['content'])
<section class="panel" aria-labelledby="content-history-heading"><h2 id="content-history-heading">Riwayat perubahan</h2><ol class="review-history">
    @php($memberNames = \App\Models\ContentHistory::memberNames($content->histories))
    @forelse($content->histories as $history)
    <li><p><strong>{{ $history->actor?->name ?? 'Sistem' }}</strong> {{ $history->describe($memberNames) }}</p><p class="hint">{{ $history->created_at->timezone(config('app.timezone'))->locale('id')->translatedFormat('d M Y H:i') }} WIB</p>@if($history->note)<p class="mt-2 whitespace-pre-wrap">{{ $history->note }}</p>@endif</li>
    @empty<li class="muted text-sm">Belum ada perubahan tercatat. Riwayat status, jadwal, dan PIC dicatat sejak fitur ini aktif.</li>@endforelse
</ol></section>
