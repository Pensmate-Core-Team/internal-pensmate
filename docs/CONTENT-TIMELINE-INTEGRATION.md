# Integrasi draf, carousel, review, dan referensi timeline

Implementasi: 19 November 2026. Timeline bulanan lokal tersedia pada `/timeline`,
dengan data, tautan, referensi dan otorisasi yang sama dengan modul konten. Feed
tetap tersedia untuk integrasi kalender rekan tim. Tidak dibuat tabel timeline
atau salinan referensi baru.

## Timeline yang sudah terintegrasi

Buka menu **Timeline konten** (`timeline.index`) setelah login. Pilih bulan,
status dan platform. Desktop memakai kalender Senin?Minggu; tablet/ponsel memakai
agenda berdasarkan tanggal agar link tetap mudah dibaca. Tiap jadwal menampilkan
jam WIB, judul, status, PIC dan jumlah referensi.

Buka jumlah referensi pada jadwal untuk melihat judul, catatan, dan link sumber.
Link eksternal dibuka di tab baru. Tambah/Kelola referensi menuju detail konten
pada #references; perubahan CRUD tersimpan langsung pada content_references dan
muncul saat timeline dimuat ulang. Detail konten mempunyai link kembali ke bulan
jadwal dan anchor item yang sama. Referensi pada konten terkunci hanya bisa dibaca.

Route HTML menerima `month=YYYY-MM`, status dan platform opsional. Navigasi bulan
mempertahankan filter. Draf tanpa scheduled_at tetap ada di Draf konten, bukan
kalender. Semua jadwal bulanan yang berizin dirender, termasuk bila lebih dari 100;
batas pagination 100 hanya berlaku pada feed JSON. Halaman berjalan tanpa JavaScript.

HTML dan feed menggunakan scope Content::visibleTo serta forTimelineRange yang
sama. Tidak ada query semua konten yang kemudian disaring di browser. Tidak ada
migration, seed, perubahan .env atau library kalender tambahan untuk integrasi ini.

## Kontrak data timeline

Route session-authenticated: `GET /integrations/timeline/contents` (`timeline.contents`).
Gunakan request same-origin dengan cookie login dan `Accept: application/json`.
Akun harus aktif dan mempunyai email. Semua hasil sudah dibatasi server sesuai akses.

Parameter:

- `start`, `end`: wajib `YYYY-MM-DD`, inklusif dalam timezone Asia/Jakarta.
- Rentang maksimal 93 hari. Konten tanpa scheduled_at tidak masuk feed.
- `status`: opsional draft/in_review/revision/ready/published.
- `platform`: opsional Instagram/TikTok/YouTube/LinkedIn/X.
- `page`: opsional, mulai 1. Maksimal 100 item per halaman.

Response memuat `data` dan `pagination` (current_page, last_page, total,
next_page_url). Ikuti next_page_url sampai null; jangan menganggap halaman pertama
mencakup seluruh jadwal.

Setiap item memuat:

- id, title, start (ISO 8601 dengan offset WIB), status, status_label,
  platform, division, revision_number, dan assignee (id/name).
- detail_url: detail draf yang sama dengan modul konten.
- references_url: detail draf dengan anchor #references.
- references: array id/title/url/notes, langsung dari relasi Content::references().
- assets: array berurutan id/name/mime_type/position/preview_url/download_url.
  download_url bernilai null sebelum approval lengkap atau bila akses tidak memenuhi.

Contoh pengambilan semua halaman:

```js
let next = '/integrations/timeline/contents?start=2026-11-01&end=2026-11-30';
const items = [];
while (next) {
    const response = await fetch(next, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error('Jadwal tidak dapat dimuat.');
    const payload = await response.json();
    items.push(...payload.data);
    next = payload.pagination.next_page_url;
}
```

Pada item kalender, tampilkan judul dan jumlah referensi. Tombol Referensi menuju
references_url; daftar link dapat memakai array references pada detail/drawer.
Tampilkan title/notes sebagai teks (textContent), jangan HTML mentah. Gunakan URL
media dari response; jangan membuat URL storage publik atau mengirim storage_path.

Untuk timeline berbasis Blade, ambil satu konten melalui scope bersama:

```php
$content = Content::visibleTo(auth()->user())
    ->with('references.creator')->findOrFail($id);
```

Kemudian render `<x-content-references :content="$content" />` pada detail konten
terpilih. Komponen mempertahankan validasi, hak edit, dan formulir referensi yang
sama. Render satu detail terpilih per halaman/drawer agar ID formulir tidak ganda.

Scope `Content::visibleTo($user)` dipakai bersama oleh daftar draf dan feed.
Admin melihat seluruh konten. Member melihat miliknya/penugasannya. Koordinator
mendapat antrean in_review dari divisi lain dan riwayat konten yang direviewnya.
Pencabutan role koordinator menghapus perluasan akses tersebut. Setiap preview,
unduhan, edit dan review tetap memeriksa otorisasi backend, bukan hanya data feed.

## Pengaturan reviewer

Admin membuka Manage member, mengisi divisi, lalu memilih peran Koordinator divisi
untuk anggota yang ditunjuk. Koordinator harus mempunyai email, password untuk
akses pertama, dan status aktif. Penunjukan tidak dilakukan otomatis dari CSV.
Nama divisi dinormalisasi whitespace; perbandingan mengabaikan kapitalisasi.
Admin dapat mengisi divisi konten pada form draf; draf member memakai divisi dari
profil anggota. Member tidak boleh memalsukan divisi atau penanggung jawab.

Sebelum submit, wajib ada caption, jadwal, divisi, dan media yang benar-benar ada
pada disk privat. Setiap submit menaikkan revision_number. Status in_review
mengunci caption/media/urutan/referensi.

Untuk mencapai ready, versi yang sama membutuhkan:

1. Satu persetujuan dari admin aktif.
2. Satu persetujuan dari koordinator aktif dari divisi lain, bukan pembuat/PIC.
3. Dua reviewer berbeda, dijamin validasi dan dua unique constraint database.

Urutan kedua persetujuan bebas. Admin dapat membuat draf dan memberi persetujuan
admin; tetap dibutuhkan koordinator lain yang independen. Satu orang tidak boleh
mengisi kedua posisi walaupun role-nya berubah di tengah review.

Permintaan revisi wajib memiliki catatan dan mengubah status menjadi revision.
Draf dapat diperbaiki lalu diajukan sebagai versi baru. Persetujuan versi lama
tidak berlaku pada versi baru. content_reviews menyimpan reviewer, role/divisi
saat keputusan, versi, keputusan, catatan dan waktu; record lama tidak ditimpa.
reviewed_by/reviewed_at/review_notes tetap tersedia sebagai metadata keputusan
terakhir untuk kompatibilitas integrasi lama.

## Carousel dan paket siap upload

Form menerima media_files[] (maksimal 10 file total per konten, setiap file 50 MB).
Format: JPG, PNG, WebP, MP4, WebM. Tambahan file ditempatkan di akhir urutan;
tombol Naik/Turun pada media tersimpan mengubah asset_order[] saat menyimpan.
remove_assets[] hanya boleh berisi ID milik draf tersebut. Edit hanya pada
status draft/revision. Tidak ada ZIP/bulk export atau posting sosial otomatis.

Setelah ready, pengguna berizin dapat mengunduh tiap file sesuai urutan, menyalin
caption, melakukan upload manual, lalu menyimpan URL postingan. Caption disalin
sebagai plain text dengan baris baru/Unicode utuh. Jika Clipboard API tidak tersedia
atau ditolak browser, UI memberi petunjuk penyalinan manual tanpa mengklaim sukses.

Route preview: contents.assets.show. Route unduh final: contents.assets.download.
Route contents.download tetap tersedia untuk media utama. Seluruh route privat,
menggunakan policy dan memeriksa kepemilikan media terhadap konten. Media belum
approved tetap dapat dipreview oleh pengguna berizin, tetapi route unduh final
menolak sebelum dua persetujuan lengkap.

content_assets menjadi sumber urutan media. media_path/media_name/media_mime pada
contents tetap mencerminkan file pertama untuk kompatibilitas. Migration menyalin
metadata media tunggal lama tanpa memindahkan file. Input media lama tetap diterima
sebagai pengganti file utama; input media_files baru menambahkan file.

Penulisan konten/media/review memakai transaksi dan penguncian konten. File baru
dibersihkan saat transaksi gagal. Penghapusan file lama dilakukan setelah commit.
Migration rollback menolak membuang tabel media/review yang masih berisi data.

Atur upload_max_filesize=50M dan post_max_size yang mencukupi seluruh carousel,
misalnya 550M untuk 10 file, beserta batas body web server. Nilai ini belum mengubah
php.ini/server pengguna. Database dan kode tidak dapat melampaui batas server.

## Verifikasi dan batas

- 60 tes / 409 assertions lulus pada SQLite in-memory, termasuk alur timeline ke
  detail, CRUD referensi, filter bulan, kalender lebih dari 100 jadwal dan privasi.
- Alur carousel, dua approval, unduhan privat, render ready dan feed referensi
  juga lulus pada transaksi MySQL yang dibatalkan. File dan record QA dibersihkan.
- 32 member serta akun admin awal dipertahankan. Tidak dibuat reviewer nyata,
  password contoh, perubahan .env, commit, atau push.
- Build Vite, kompilasi Blade dan pemeriksaan JavaScript/clipboard helper lulus.
- Rendering kalender dan perubahan referensi baru/ubah/hapus juga lulus pada
  transaksi MySQL yang di-rollback; data pengguna dan jumlah record awal tetap utuh.
- Halaman timeline lokal sudah terintegrasi secara fungsional. Pemeriksaan visual
  desktop/mobile melalui browser belum tersedia pada sesi ini. Kalender eksternal
  rekan tim tetap dapat memakai feed, tetapi bukan syarat penggunaan halaman lokal.

## Kartu timeline ringkas

Kartu memakai jam singkat, badge status pendek, judul satu baris, dan metadata
platform/PIC satu baris. Teks lengkap tetap ada pada tooltip dan detail konten.
Daftar referensi dibuka sebagai panel mengambang agar tidak menambah tinggi kartu;
link dan tindakan kelola tetap menggunakan data/otorisasi yang sama. Kalender
beralih ke agenda pada lebar sampai 1279px agar kolom laptop tidak terlalu sempit.
