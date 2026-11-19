@props(['member', 'activation' => null])
<section class="panel">
    <h2>Aktivasi akun member</h2>
    @if($member->email)
        <p class="hint mt-3">Member sudah memiliki email login. Link aktivasi tidak dapat dipakai untuk mereset akun yang sudah tersedia.</p>
    @elseif(!$member->is_active)
        <p class="hint mt-3">Aktifkan status member melalui Edit member sebelum membuat link aktivasi.</p>
    @else
        <p class="hint mt-3">Member membuat email dan password sendiri melalui link personal. Link berlaku 24 jam dan sekali pakai.</p>
        @if($activation && $activation->isAvailable())
            <p class="hint mt-3">Ada link yang belum digunakan, berlaku sampai {{ $activation->expires_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB.</p>
        @elseif($activation)
            <p class="hint mt-3">Link sebelumnya sudah tidak berlaku. Kamu dapat membuat link baru.</p>
        @endif
        <div class="mt-5 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('members.activation.store', $member) }}" @if($activation && $activation->isAvailable()) data-confirm="Buat link baru? Link aktivasi sebelumnya akan dibatalkan." @endif>@csrf<button class="button button-primary" data-loading="Membuat link...">{{ $activation && $activation->isAvailable() ? 'Buat link baru' : 'Buat link aktivasi' }}</button></form>
            @if($activation && $activation->isAvailable())<form method="POST" action="{{ route('members.activation.destroy', $member) }}" data-confirm="Cabut link aktivasi ini? Link tidak dapat digunakan lagi.">@csrf @method('DELETE')<button class="button button-secondary danger" data-loading="Mencabut link...">Cabut link</button></form>@endif
        </div>
    @endif
</section>
