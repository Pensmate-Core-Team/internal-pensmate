# Pensmate — modul konten dan member

Project: `C:\Users\Ahmad Syahani\Desktop\internal-pensmate`

## Yang tersedia

- Login/logout, pembatasan percobaan login, dan penolakan akun nonaktif.
- Draf konten: judul, caption, platform, penanggung jawab, jadwal WIB, dan file media.
- File JPG, PNG, WebP, MP4, WebM hingga 50 MB, mengikuti batas PHP/server. Media disimpan privat dan hanya dibuka melalui route berotorisasi.
- Alur draf, review, revisi, siap upload, dan terunggah. Member tidak dapat menyetujui draf sendiri.
- Referensi per konten: judul, URL HTTP/HTTPS, catatan, edit, hapus, dan pembuat referensi.
- Manage member: tambah, edit, role, status aktif/nonaktif, ganti password, pencarian, filter, serta jumlah konten yang ditugaskan.
- Perlindungan agar admin tidak menonaktifkan atau menurunkan role akun sendiri. Minimal satu admin aktif harus dipertahankan.
- Desain meja editorial dengan Tailwind CSS, navigasi mobile, fokus keyboard, status, dan empty state.

## Preview demo

URL saat server berjalan: http://127.0.0.1:8765/login

| Role | Email | Password demo lokal |
|---|---|---|
| Admin | admin@pensmate.test | Pensmate2026! |
| Member | sheva@pensmate.test | Pensmate2026! |
| Member | galang@pensmate.test | Pensmate2026! |

Database demo terpisah di folder kerja chat. Akun dan draf demo tidak dimasukkan ke MySQL project. Server preview hanya terikat ke localhost. Password di atas hanya untuk demo lokal.

## Menjalankan project dengan database MySQL sendiri

Migrasi tambahan telah dijalankan pada database `internal-pm` di `127.0.0.1:3306`, sesuai persetujuan pengguna. Tidak ada seed data pada database ini. User lama memperoleh role `member` dan status aktif secara default.

Jalankan dari direktori project:

```powershell
php artisan pensmate:admin
npm run build
php -d upload_max_filesize=50M -d post_max_size=55M artisan serve --host=127.0.0.1 --port=8001
```

Command pertama meminta nama, email, dan password secara interaktif. Gunakan akun tersebut pada server MySQL, bukan akun demo SQLite.

Untuk pengembangan frontend, jalankan `npm run dev` di terminal lain. `npm run build` menghasilkan aset yang bisa dipakai tanpa Vite dev server.

Pada checkout baru, pasang dependency dan jalankan migrasi terlebih dahulu:

```powershell
composer install
npm ci
php artisan migrate
```

## Alur penggunaan

1. Admin menambahkan member melalui Manage member.
2. Admin/member membuat draf. Member hanya melihat konten yang dibuat atau ditugaskan kepadanya.
3. Simpan draf, lalu tambahkan referensi dari halaman detail.
4. Lengkapi caption, media, dan jadwal sebelum menekan Ajukan review.
5. Admin menyetujui atau meminta revisi. Catatan wajib saat meminta revisi.
6. Konten yang disetujui berstatus Siap upload. Materi dikunci selama review dan setelah persetujuan.
7. Upload manual ke platform, lalu masukkan URL postingan dan tandai Terunggah.

Menambahkan member membuat akun langsung. Belum ada pengiriman email undangan atau reset password via email; admin mengganti password melalui Edit member.

## Kontrak integrasi tim

Gunakan model `App\Models\Content` dan tabel `contents` sebagai sumber data bersama. Jangan membuat tabel draf/timeline duplikat.

| Field/relasi | Pemakaian |
|---|---|
| `id` | Identitas konten yang digunakan timeline |
| `title`, `caption`, `platform` | Materi konten |
| `scheduled_at` | Jadwal dengan konfigurasi aplikasi Asia/Jakarta |
| `created_by`, `creator` | Pembuat konten |
| `assigned_to`, `assignee` | Penanggung jawab konten |
| `status` | Tahap produksi |
| `references` | Daftar referensi terkait konten |
| `published_url`, `published_at` | Bukti publikasi manual |

Status yang disepakati: `draft`, `in_review`, `revision`, `ready`, `published`. Label UI tersedia di `Content::STATUSES`.

Contoh query untuk modul timeline:

```php
$contents = Content::with(['assignee', 'references'])
    ->whereNotNull('scheduled_at')
    ->orderBy('scheduled_at')
    ->get();
```

Query harus tetap dibatasi sesuai hak akses pengguna. `ContentPolicy::view` mengizinkan admin, pembuat, atau penanggung jawab; middleware menolak akun nonaktif. Timeline belum dibuat dalam lingkup ini. Tombol dari timeline dapat menuju route `contents.show` dan bagian `#references`.

Plotting job/absensi memakai `App\Models\User` yang sama. `assigned_to` merupakan penanggung jawab konten, bukan pengganti tabel pembagian job yang lebih rinci. Tim Galang tetap menentukan struktur job/assignment mereka.

Route utama: `contents.*`, `contents.transition`, `contents.media`, `references.store/update/destroy`, dan `members.*`. Perubahan status memakai POST dengan field `action` bernilai `submit`, `approve`, `revise`, atau `publish`.

## Validasi

- 11 test lulus, 93 assertion, menggunakan SQLite dalam memori.
- Test memaksa `APP_ENV=testing` dan database memori sebelum Laravel boot, termasuk ketika shell mewariskan konfigurasi lain.
- Login HTTP nyata dengan CSRF dan lima halaman terproteksi berhasil pada server demo.
- Build Vite berhasil. Kode PHP dirapikan dengan Laravel Pint.
- Pemeriksaan screenshot mencakup daftar draf, detail, formulir, member, dan login pada desktop/mobile; daftar draf juga diperiksa di lebar 1024, 768, dan 320.
- Konfigurasi PDO bawaan disesuaikan agar tidak memunculkan deprecation di PHP 8.5, sambil mempertahankan dukungan versi PHP sebelumnya.

## Batas lingkup

Belum mencakup posting otomatis ke media sosial, timeline kalender, poin keaktifan, absensi, plotting job, notifikasi/email, atau riwayat review berulang. Link referensi sudah terkait konten dan siap ditampilkan oleh modul timeline rekan tim.

Perubahan belum di-commit atau di-push. Cadangan file awal tersimpan di folder kerja chat `work/backup-original`.
