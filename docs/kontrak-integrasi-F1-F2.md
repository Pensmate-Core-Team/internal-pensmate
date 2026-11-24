# Kontrak Integrasi — Timeline Konten (F1) dan Poin Keaktifan (F2) dengan modul Konten dan Manage Member

**Status:** Usulan, menunggu persetujuan pengembang modul Konten/Member\
**Tanggal:** 24 November 2026\
**Dasar kode:** branch `feature` (commit `0282aca`)\
**Keputusan produk:** PRD Modul Timeline Konten dan Poin Keaktifan v1.2, bagian 13

## Tujuan

F1 dan F2 dibangun **di atas** model `Content` dan `User` yang sudah ada, tanpa tabel konten atau anggota tandingan (sesuai `docs/CONTENT-WORKSPACE.md`). Dokumen ini mencatat:

1. bagian kode kalian yang kami andalkan, jadi perlu tetap stabil;
2. perubahan yang kami minta di kode kalian;
3. tambahan dari F1/F2 yang tidak mengubah perilaku kalian;
4. pertanyaan terbuka.

Kalau ada poin yang tidak cocok, ubah dokumen ini dulu sebelum mengubah kode, supaya kedua pihak tahu.

## 1. Yang kami andalkan (mohon tetap stabil)

| Bagian | Yang dipakai F1/F2 |
| --- | --- |
| `contents.status` | Nilai `draft`, `in_review`, `revision`, `ready`, `published` dan label di `Content::STATUSES`. Jangan ganti nama nilai yang sudah ada. Kalau ada status baru, kabari kami. |
| `contents.scheduled_at` | Tenggat atau jadwal terbit untuk timeline dan penanda lewat tenggat |
| `contents.assigned_to`, `created_by`, `division`, `platform` | Filter timeline, penanda butuh PIC, dan sumber poin |
| `contents.published_url`, `published_at` | Bukti terbit |
| `content_reviews` | Riwayat keputusan review; dibaca F1, tidak diubah |
| `users.role` | Nilai `member`, `coordinator`, `admin`. Kami memetakan `admin` = BPH. Jangan ganti nama nilai yang sudah ada. |
| `users.division` | Teks yang dinormalisasi lewat `User::divisionKey()` |
| `users.is_active` + `SoftDeletes` | Penanda PIC nonaktif atau diarsipkan; relasi riwayat memakai `withTrashed()` |
| `TransitionContent`, `SaveContent` | Tetap menjadi satu-satunya jalur untuk mengubah status dan isi konten |

**Aturan penting untuk riwayat (bagian 3.1):** ubah `status`, `scheduled_at`, dan `assigned_to` **selalu lewat model Eloquent** (`$content->save()`), bukan lewat `DB::table()->update()`, `Content::query()->update()`, atau `saveQuietly()`. Kalau tidak lewat model, event-nya tidak terpicu dan perubahan itu tidak masuk riwayat.

## 2. Perubahan yang kami minta di kode kalian

### 2.1 Visibilitas konten dibuka untuk semua anggota aktif (PRD K-TL6)

Keputusan produk: semua anggota aktif yang punya akses login dapat **melihat** semua konten, termasuk detail, caption, dan pratinjau media.

| Lokasi | Perubahan |
| --- | --- |
| `Content::scopeVisibleTo` | Akun aktif dengan email melihat semua konten. Akun nonaktif atau tanpa email tetap tidak melihat apa pun. |
| `ContentPolicy::view` | Sama dengan di atas |
| `ContentPolicy::update`, `review`, `publish` | **Tidak berubah** |
| `ContentPolicy::download` | **Tidak berubah**, tetap mensyaratkan status `ready`/`published` dan dua persetujuan. Karena `view` dibuka, unduhan media final juga terbuka untuk semua anggota aktif setelah disetujui. Kalau kalian ingin unduhan tetap terbatas pada pembuat, PIC, admin, dan reviewer, ubah `download` agar tidak bergantung pada `view`. Mohon kabari pilihan kalian. |
| Tes | Tes privasi yang mengharapkan member tidak bisa membaca konten anggota lain perlu disesuaikan |

Siapa yang mengerjakan perlu disepakati. Usulan kami: F1 membuat PR kecil khusus perubahan ini, lalu kalian review.

## 3. Tambahan dari F1/F2 (tidak mengubah perilaku kalian)

Semua migrasi F1/F2 memakai timestamp **setelah** `2026_11_19_000003`.

### 3.1 Riwayat perubahan konten (F1, PRD K-TL7)

- Tabel baru `content_histories`: `content_id`, `actor_id` (nullable untuk proses sistem), `field` (`status` / `scheduled_at` / `assigned_to`), `old_value`, `new_value`, `note`, `created_at`.
- Diisi oleh **observer** `ContentHistoryObserver` pada event `created` (status awal) dan `updated` milik `Content`. Observer didaftarkan dengan satu baris, yaitu atribut `#[ObservedBy]` pada `Content` atau `Content::observe()` di `AppServiceProvider`. `TransitionContent` dan `SaveContent` **tidak perlu diubah**.
- Pelaku diambil dari user yang sedang login. Catatan revisi diambil dari `review_notes` saat status menjadi `revision`.

### 3.2 Halaman timeline (F1)

- `/timeline` dan feed `/integrations/timeline/contents` milik kalian **dipertahankan**. F1 menambah tampilan daftar dan papan status, filter divisi, PIC, dan rentang tanggal, serta penanda lewat tenggat dan butuh PIC.
- Bila perlu mengubah `TimelineController`, `forTimelineRange`, atau view `timeline/index.blade.php`, kami ajukan lewat PR yang kalian review.

### 3.3 Kolom tambahan di `contents` (F1, menunggu keputusan pengurus)

PRD meminta kolom brief dan pilar konten. Kalau pengurus menyetujui, F1 menambah `brief` (text, nullable) dan `pillar` (string, nullable). Keduanya opsional dan tidak memengaruhi validasi pengajuan review kalian.

### 3.4 Poin Keaktifan (F2)

Tabel baru milik F2: `point_rules`, `point_transactions`, dan `point_correction_requests`. Tabel ini mereferensikan `users.id` dengan `restrictOnDelete()` (cocok dengan soft delete kalian) dan menyimpan salinan `users.division` saat transaksi dicatat. Konten dirujuk secara polimorfik (`source_type` = `content`, `source_id` = `contents.id`). Tidak ada perubahan pada tabel kalian.

### 3.5 Usulan audit log bersama

Usulan tabel `audit_logs` untuk semua modul: `actor_id`, `action`, `auditable_type`, `auditable_id`, `old_values` json, `new_values` json, `reason` nullable, `created_at`. F2 membutuhkannya untuk perubahan aturan poin dan koreksi. Perubahan role, divisi, dan status aktif di Manage Member sebaiknya ikut tercatat di sini. Siapa pun yang lebih dulu membutuhkannya membuat tabel ini, lalu modul lain ikut memakai.

## 4. Pertanyaan terbuka

1. **Unduhan media final:** apakah ikut terbuka untuk semua anggota aktif setelah disetujui (otomatis, karena `view` dibuka), atau tetap terbatas (lihat 2.1)?
2. **Daftar divisi:** `division` saat ini teks bebas, jadi salah ketik bisa membuat "divisi" baru dan mengacaukan filter timeline, aturan reviewer lintas divisi, dan rekap poin. Apakah kalian mau menambah daftar divisi tetap, misalnya konstanta atau config `Kreatif`, `Produksi`, `Public Relations` sebagai pilihan dropdown?
3. **Rangkap peran:** apakah ada anggota yang perlu menjadi koordinator di dua divisi, atau admin sekaligus koordinator? Saat ini `role` dan `division` masing-masing hanya satu nilai.
4. **Admin teknis** ditunda (PRD K-R2). Sementara itu, akun `admin` dari `php artisan pensmate:admin` dianggap BPH. Apakah sesuai dengan rencana deployment kalian?
