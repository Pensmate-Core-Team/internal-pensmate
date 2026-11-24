# Desain — Timeline Konten MVP (riwayat, penanda, daftar/papan, filter)

**Tanggal:** 24 November 2026\
**Branch:** `feature-timeline-poin` (dari `origin/feature` @ `0282aca`)\
**Sumber kebutuhan:** PRD Modul Timeline Konten dan Poin Keaktifan v1.2 (bagian 5 dan 13), `docs/kontrak-integrasi-F1-F2.md`

## 1. Maksud

PENSmate butuh satu tempat untuk melihat rencana konten: siapa PIC-nya, apa statusnya, kapan tenggatnya, dan apa yang tertinggal, tanpa harus menanyakannya lewat chat. Modul Konten buatan dev lain sudah menyediakan data (`contents`) dan kalender bulanan `/timeline`. Pekerjaan ini menambahkan bagian Timeline MVP yang belum ada dan **tidak bergantung pada jawaban dev lain** atas dokumen kontrak.

## 2. Tujuan, bukan tujuan, anti-tujuan

**Tujuan**

1. Riwayat perubahan status, jadwal, dan PIC per konten (TL-F5, TL-F9, PRD K-TL7).
2. Penanda "Lewat tenggat" dan "Butuh PIC" yang terlihat di semua tampilan timeline dan di detail konten (TL-F6, K-TL4, K-TL5).
3. Tampilan **Daftar** dan **Papan status** di samping kalender (TL-F2, TL-F3).
4. Filter divisi, PIC, dan rentang tanggal, ditambah filter penanda (TL-F4).

**Bukan tujuan**

- Membuka visibilitas konten (K-TL6). Ini menunggu persetujuan dev lain. Semua query tetap lewat `Content::visibleTo`, jadi begitu scope itu dibuka, timeline otomatis ikut terbuka.
- Kolom brief/pilar, notifikasi (TL-F10), status yang bisa diatur admin (TL-F8), dan Poin Keaktifan.
- Mengubah alur status, aturan approval, atau aturan edit milik modul Konten.
- Drag-and-drop di papan. Perpindahan status tetap lewat tombol di detail konten, karena setiap transisi punya syarat (catatan revisi, URL posting, dua approval).

**Anti-tujuan** (dianggap gagal walaupun fiturnya berjalan)

- Merusak tes dev lain yang sudah ada atau mengubah perilaku `/contents`, `/timeline`, maupun feed JSON untuk pengguna saat ini.
- Riwayat yang bisa dilewati diam-diam, misalnya perubahan status yang tidak tercatat.
- Penanda yang hanya berupa warna.
- Halaman yang menggulir menyamping di ponsel.

## 3. Batasan

- Laravel 12, Blade, Tailwind CSS 4, Vite. Halaman timeline yang ada berjalan tanpa JavaScript; tampilan baru juga harus begitu.
- Ikuti `DESIGN.md` dan kelas CSS yang sudah ada (`page-heading`, `timeline-filters`, `content-list`, `content-row`, `x-status`, `x-icon`, `empty-state`, dll.).
- Zona waktu Asia/Jakarta (`config('app.timezone')`), label "WIB".
- Bahasa antarmuka Indonesia.
- Tes memakai SQLite in-memory dengan pola yang sama dengan `tests/Feature/TimelinePageTest.php` (`RefreshDatabase`, `withoutVite()`, `forceFill` untuk `created_by`/`status`).
- Perubahan pada file milik dev lain dibuat sekecil mungkin dan disebutkan di deskripsi PR: `timeline/index.blade.php`, `contents/show.blade.php`, `TimelineController`, `Content`, `AppServiceProvider`.

## 4. Pendekatan

| Pendekatan | Untung | Rugi |
| --- | --- | --- |
| **A. Halaman terpisah `/timeline/list` dan `/timeline/board` dengan kontroler sendiri, ditambah komponen filter dan tab bersama** | Kode dev lain hampir tidak tersentuh; risiko konflik merge dan regresi tes paling kecil | Tiga route untuk satu fitur; logika filter harus dipusatkan supaya tidak tertulis tiga kali |
| B. Satu `TimelineController` dengan `?view=calendar\|list\|board` | URL rapi, satu kontroler | Mengubah banyak kontroler dan view milik dev lain; tes mereka ikut terdampak |

**Dipilih: A** (oleh pengguna, 24 November 2026).

## 5. Desain

### 5.1 Riwayat perubahan

**Tabel `content_histories`**

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id` | bigint PK | |
| `content_id` | FK `contents.id`, `restrictOnDelete` | Konten tidak dihapus di aplikasi ini |
| `actor_id` | FK `users.id`, nullable, `restrictOnDelete` | Null bila tidak ada user login (artisan, seeder) |
| `field` | string(32) | `status`, `scheduled_at`, `assigned_to` |
| `old_value` | string, nullable | Status: nilai mentah. Jadwal: ISO 8601 (WIB). PIC: user id |
| `new_value` | string, nullable | Sama dengan `old_value` |
| `note` | text, nullable | Saat status menjadi `revision`, diisi `review_notes`; selain itu null |
| `created_at` | timestamp | Tanpa `updated_at`; baris riwayat tidak pernah diubah |

Indeks: `(content_id, created_at)`.

**Pengisian:** `App\Observers\ContentHistoryObserver`, didaftarkan lewat atribut `#[ObservedBy]` pada `Content` atau `Content::observe()` di `AppServiceProvider`. Builder bebas memilih; yang penting satu baris saja.

- `created`: satu baris `status` dengan `old_value = null`, `new_value = status awal` (biasanya `draft`).
- `updated`: satu baris untuk setiap field yang diawasi dan benar-benar berubah (`wasChanged`). Nilai lama diambil dari `getOriginal()` / `getPrevious()`.
- Aktor diambil dari `auth()->id()`.
- Observer menulis di dalam transaksi yang sama dengan penyimpanan konten (`TransitionContent` dan `SaveContent` sudah memakai `DB::transaction`), jadi kalau transaksi gagal, riwayatnya ikut batal.

**Model** `App\Models\ContentHistory` dengan relasi `content()` dan `actor()` (`withTrashed`). Di `Content`: relasi `histories()`.

**Tampilan:** pada `contents/show.blade.php` ditambah panel "Riwayat perubahan" di dekat panel "Riwayat review", diurutkan dari yang terbaru. Format tiap baris: *"{aktor atau Sistem} mengubah status Draf → Dalam review · 24 Nov 2026 10:15 WIB"*, lalu catatan bila ada. PIC ditampilkan dengan nama (relasi ke user, termasuk yang diarsipkan), jadwal dengan format `d M Y H:i WIB`. Bila belum ada riwayat, tampilkan "Belum ada perubahan tercatat".

**Data lama:** tidak dibuat riwayat tiruan untuk konten yang sudah ada sebelum migrasi. Riwayat mulai tercatat sejak fitur ini dirilis, dan hal ini dijelaskan di halaman (lihat empty state).

### 5.2 Penanda

Tambahan di model `Content`:

- `isOverdue(): bool`: `scheduled_at` tidak null, sudah lewat dari `now()`, dan `status !== 'published'`.
- `needsAssignee(): bool`: `status !== 'published'` dan PIC **tidak bisa memegang konten**, yaitu nonaktif (`is_active = false`), diarsipkan (`trashed()`), atau belum punya akses login (`email` kosong, anggota direktori yang belum aktivasi). Relasi `assignee` sudah memakai `withTrashed()`. Definisi ini lebih luas daripada PRD K-TL4 (keputusan pengguna, 24 November 2026); K-TL4 di PRD diperbarui sesuai.
- Scope `overdue()` dan `needsAssignee()` dengan logika yang sama, untuk filter.

**Komponen `<x-content-flags :content="$content" />`** merender nol, satu, atau dua badge berisi ikon dan teks: "Lewat tenggat" dan "Butuh PIC". Komponen ini dipakai di kartu kalender, baris daftar, kartu papan, dan header detail konten. Teks selalu tampil (bukan hanya warna/ikon). Di kartu kalender yang sempit boleh memakai teks pendek ("Telat", "PIC?") asalkan ada `aria-label` dan `title` berisi teks lengkap.

### 5.3 Filter bersama

**Objek `App\Support\TimelineFilters`** (atau Form Request `TimelineFilterRequest` dengan method `apply(Builder)`; builder bebas memilih bentuknya) memusatkan validasi dan penerapan filter supaya ketiga tampilan konsisten.

| Parameter | Validasi | Efek |
| --- | --- | --- |
| `status` | salah satu kunci `Content::STATUSES` | `where status` (tidak berlaku di papan, karena papan sudah dikelompokkan per status) |
| `platform` | salah satu `Content::PLATFORMS` | `where platform` |
| `division` | string ≤ 120 | Dibandingkan dengan `LOWER(TRIM(division))` = `User::divisionKey(input)` |
| `assignee` | integer, user ada (termasuk yang diarsipkan) | `where assigned_to` |
| `from`, `to` | `Y-m-d`, `to` ≥ `from`; keduanya opsional | Rentang `scheduled_at` inklusif dalam WIB (awal hari `from` sampai akhir hari `to`). Bila salah satu diisi, konten tanpa jadwal tidak ikut. Hanya untuk daftar dan papan; kalender tetap memakai `month`. |
| `flag` | `overdue` atau `needs_assignee` | Menerapkan scope penanda |

**Pilihan dropdown**

- Divisi: nilai `division` yang berbeda dari konten yang terlihat oleh user (`visibleTo`), dikelompokkan dengan `divisionKey`, lalu ditampilkan memakai salah satu ejaan aslinya.
- PIC: user yang menjadi `assigned_to` pada konten yang terlihat oleh user, termasuk yang nonaktif atau diarsipkan (diberi tanda "(nonaktif)") dan yang belum punya akses login (diberi tanda "(belum aktivasi)"), diurutkan menurut nama.

Kedua pilihan diambil dari konten yang terlihat supaya filter tidak membocorkan nama atau divisi yang tidak boleh dilihat.

**Kalender yang sudah ada** ikut menerima `division`, `assignee`, dan `flag`. Perubahannya di `TimelineController::index` hanya menerapkan objek filter bersama selain `status`/`platform` yang sudah ada, dan `month` tetap dipertahankan saat berpindah bulan.

### 5.4 Navigasi tampilan

**Komponen `<x-timeline-tabs current="calendar|list|board" />`** berisi tiga tautan: "Kalender", "Daftar", dan "Papan". Tautan aktif diberi `aria-current="page"`. Saat berpindah tampilan, filter yang berlaku di keduanya dibawa (`status`, `platform`, `division`, `assignee`, `flag`). Parameter `month` dan `from`/`to` tidak dibawa, karena maknanya berbeda.

Komponen ini dan form filter bersama disisipkan di `timeline/index.blade.php` milik dev lain, tepat di bawah `page-heading`. Form filter kalender yang ada diganti dengan komponen `<x-timeline-filter-form>` yang sama dengan yang dipakai daftar dan papan, dengan slot untuk field `month` (kalender) atau `from`/`to` (daftar/papan).

Item sidebar "Timeline konten" tetap aktif di ketiga halaman, karena semuanya memakai prefix route `timeline.*`.

### 5.5 Tampilan Daftar — `GET /timeline/list` (`timeline.list`)

- `TimelineListController` (invokable atau `index`), dengan query `Content::visibleTo($user)` + filter + `with('assignee')` + `withCount('references')`.
- **Urutan** lewat `sort`:
  - `scheduled` (default): `scheduled_at` naik, konten tanpa jadwal di akhir, lalu `id`.
  - `status`: urutan alur sesuai `Content::STATUSES` (`draft`, `in_review`, `revision`, `ready`, `published`), sama dengan urutan kolom papan, lalu `scheduled_at`. *(Keputusan builder setelah review.)*
  - `-scheduled`: jadwal turun.

  Urutan status memakai ekspresi `CASE` yang aman di SQLite dan MySQL.
- Paginasi 25, `withQueryString()`.
- **Kolom:** judul (tautan ke detail) + platform + jumlah referensi, status (`x-status`), penanda (`x-content-flags`), PIC, divisi, jadwal (tanggal + jam WIB, atau "Belum dijadwalkan").
- Memakai pola markup `content-list` / `content-row` dari `contents/index.blade.php` supaya responsif seperti yang sudah ada. Kolom divisi dan penanda boleh digabung ke baris meta di layar sempit.
- Ringkasan kecil di atas daftar berisi jumlah total, lewat tenggat, dan butuh PIC (dihitung dari query yang sama tanpa filter `flag`). Setiap angka menjadi tautan yang menerapkan filter `flag` tersebut.
- Empty state yang jujur, dibedakan antara "tidak ada konten" dan "tidak ada yang cocok dengan filter".
- Header `Cache-Control: private, no-store`, sama seperti kalender.

### 5.6 Tampilan Papan — `GET /timeline/board` (`timeline.board`)

- `TimelineBoardController`.
- Lima kolom sesuai urutan `Content::STATUSES`: Draf, Dalam review, Perlu revisi, Siap upload, Terunggah. Judul kolom memakai label status dan jumlah kartu.
- **Batas data:**
  - Kolom selain Terunggah menampilkan semua konten yang cocok dengan filter.
  - Kolom **Terunggah** hanya menampilkan konten yang `published_at`-nya dalam 30 hari terakhir, kecuali bila `from` **atau** `to` diisi. Dalam kasus itu, batas 30 hari dilepas dan kolom ini disaring dengan `scheduled_at` seperti kolom lain, supaya semua kolom memakai arti tanggal yang sama. Catatan kecil "Menampilkan yang terunggah 30 hari terakhir" hanya muncul saat batas itu berlaku. *(Keputusan builder setelah review.)*
  - Setiap kolom menampilkan maksimal 50 kartu. Bila lebih, ada tautan "Lihat semua di Daftar" yang membuka tampilan daftar dengan filter `status` kolom itu.
- **Urutan kartu:** `scheduled_at` naik (tanpa jadwal di akhir), lalu `id`.
- **Kartu:** judul (tautan ke detail), jadwal (tanggal + jam WIB atau "Belum dijadwalkan"), platform · PIC, penanda, jumlah referensi.
- **Tata letak:**
  - Desktop lebar: kolom berdampingan dalam grid lima kolom. Kolom boleh menggulir vertikal di dalamnya, tetapi halaman tidak menggulir menyamping.
  - Di bawah breakpoint yang sama dengan kalender (1279px): kolom ditumpuk vertikal sebagai bagian-bagian berjudul.
- Tidak ada drag-and-drop dan tidak ada tombol aksi di kartu (lihat bukan tujuan).

### 5.7 Route

Ditambahkan di grup `auth` + `EnsureActiveMember` pada `routes/web.php`, **sebelum** route yang memakai parameter:

```php
Route::get('/timeline/list', TimelineListController::class)->name('timeline.list');
Route::get('/timeline/board', TimelineBoardController::class)->name('timeline.board');
```

### 5.8 Tes

Feature test baru dengan pola fixture dari `TimelinePageTest`:

- **`ContentHistoryTest`**
  - Pembuatan konten mencatat status awal.
  - Setiap aksi `TransitionContent` (submit, approve sampai ready, revise dengan catatan, publish) mencatat status lama/baru dan aktor yang benar.
  - Perubahan jadwal dan PIC lewat `SaveContent` tercatat.
  - Perubahan field lain (judul, caption) tidak tercatat.
  - Transaksi yang gagal tidak meninggalkan riwayat.
  - Panel riwayat tampil di detail konten dengan nama aktor, termasuk aktor yang sudah diarsipkan.
- **`ContentFlagsTest`**
  - Lewat tenggat (batas waktu memakai `travelTo`; konten `published` tidak ditandai).
  - Butuh PIC (PIC nonaktif dan PIC diarsipkan; konten `published` tidak ditandai).
  - Scope filter sesuai dengan method.
  - Badge memuat teks.
- **`TimelineListTest`**
  - Hanya konten dari `visibleTo` yang tampil (member A tidak melihat milik member B).
  - Setiap filter.
  - Validasi `from` > `to` ditolak.
  - Ketiga urutan.
  - Paginasi membawa query string.
  - Dropdown divisi/PIC tidak memuat nilai dari konten yang tidak terlihat.
  - Ringkasan dan tautan flag.
  - Empty state.
- **`TimelineBoardTest`**
  - Pengelompokan per status.
  - Batas 30 hari untuk Terunggah dan pengecualiannya bila ada rentang tanggal.
  - Batas 50 kartu beserta tautan ke daftar.
  - Privasi `visibleTo`.
  - Filter.
- **Kalender:** tes yang ada tetap lulus tanpa diubah. Tes baru memastikan filter `division`/`assignee`/`flag` bekerja dan tab tampilan muncul dengan filter terbawa.
- **Seluruh suite** (`php artisan test`) lulus sebelum dan sesudah perubahan.

## 6. Diserahkan ke builder

- Nama kelas dan file akhir, serta bentuk objek filter (lihat 5.3).
- Detail CSS di dalam batas `DESIGN.md`, termasuk warna badge penanda (dengan kontras AA). Kalau perlu token warna baru, tambahkan ke `resources/css/app.css` dengan pola yang sama.
- Cara memastikan nilai `scheduled_at` lama dan baru di riwayat konsisten zona waktunya.
- Apakah ringkasan angka di daftar juga ditampilkan di papan.
