# Pensmate — modul konten dan member

Project: `C:\Users\Ahmad Syahani\Desktop\internal-pensmate`

## Yang tersedia

- Login/logout, pembatasan percobaan login, dan penolakan akun nonaktif.
- Draf konten: judul, caption, platform, penanggung jawab, divisi, jadwal WIB, dan carousel hingga 10 file dengan urutan tersimpan.
- File JPG, PNG, WebP, MP4, WebM hingga 50 MB, mengikuti batas PHP/server. Media disimpan privat dan hanya dibuka melalui route berotorisasi.
- Alur draf, review, revisi, siap upload, dan terunggah. Siap upload wajib mendapat dua persetujuan per versi: admin dan koordinator divisi lain, dari dua orang berbeda. Riwayat keputusan tersimpan.
- Referensi per konten: judul, URL HTTP/HTTPS, catatan, edit, hapus, dan pembuat referensi. Timeline bulanan `/timeline` menampilkan jadwal beserta link referensi langsung; feed JSON tetap tersedia.
- Manage member: grid kartu responsif, tambah, detail, edit, arsip, generasi, kelas, prodi, status aktif/nonaktif, pencarian dan filter generasi/status. Email dan akses login bersifat opsional.
- Admin dikecualikan dari daftar, hitungan, dan route detail/edit/hapus member. Role admin dibuat melalui command terpisah, bukan melalui CRUD member.
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
php -d upload_max_filesize=50M -d post_max_size=550M artisan serve --host=127.0.0.1 --port=8001
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
4. Lengkapi caption, media, jadwal dan divisi sebelum menekan Ajukan review.
5. Admin dan koordinator divisi lain memberi keputusan terpisah. Catatan wajib saat meminta revisi; pengajuan ulang membutuhkan dua persetujuan baru.
6. Setelah dua persetujuan lengkap, konten berstatus Siap upload. Unduh media sesuai urutan dan salin caption. Materi dikunci selama review dan setelah persetujuan.
7. Upload manual ke platform, lalu masukkan URL postingan dan tandai Terunggah.

Member dapat disimpan tanpa email/password sebagai data direktori. Untuk memberi akses login pertama kali, admin wajib mengisi email dan password minimal 10 karakter yang mengandung huruf serta angka. Tidak ada email palsu atau password bawaan untuk data impor. Belum ada email undangan/reset password; admin mengelola password melalui Edit member.

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

Query harus tetap dibatasi sesuai hak akses pengguna. `ContentPolicy::view` membatasi admin, pembuat, PIC dan reviewer berizin; middleware menolak akun nonaktif. Timeline bulanan lokal tersedia pada `timeline.index`, memakai query terotorisasi yang sama dengan feed. Link referensi terbuka langsung dari jadwal dan tindakan kelola menuju `contents.show#references`.

Plotting job/absensi memakai `App\Models\User` yang sama. `assigned_to` merupakan penanggung jawab konten, bukan pengganti tabel pembagian job yang lebih rinci. Tim Galang tetap menentukan struktur job/assignment mereka.

Route utama: `timeline.index`, `timeline.contents`, `contents.*`, `contents.transition`, `contents.media`, `references.store/update/destroy`, dan `members.*`. Perubahan status memakai POST dengan field `action` bernilai `submit`, `approve`, `revise`, atau `publish`.

## Validasi

- 60 test lulus, 409 assertion, menggunakan SQLite dalam memori (19 November 2026). Alur carousel/approval/download/feed juga diverifikasi lewat transaksi MySQL yang di-rollback.
- Test memaksa `APP_ENV=testing` dan database memori sebelum Laravel boot, termasuk ketika shell mewariskan konfigurasi lain.
- Login HTTP nyata dengan CSRF dan lima halaman terproteksi berhasil pada server demo.
- Build Vite berhasil. Kode PHP dirapikan dengan Laravel Pint.
- Pemeriksaan screenshot mencakup daftar draf, detail, formulir, member, dan login pada desktop/mobile; daftar draf juga diperiksa di lebar 1024, 768, dan 320.
- Konfigurasi PDO bawaan disesuaikan agar tidak memunculkan deprecation di PHP 8.5, sambil mempertahankan dukungan versi PHP sebelumnya.

## Batas lingkup

Belum mencakup posting otomatis ke media sosial, poin keaktifan, absensi, plotting job, atau notifikasi/email. Riwayat review per versi dan timeline bulanan dengan referensi sudah tersedia. Feed tetap dapat dipakai kalender rekan tim tanpa duplikasi data.

Panduan instalasi dan pembaruan tersedia di [SETUP.md](SETUP.md). Data member dan media lokal tidak ikut repository.

## Impor Gen 1 dan Gen 2 (19 November 2026)

32 data sumber sudah diimpor ke MySQL: 13 Gen 1 dan 19 Gen 2. Gen 1 menyimpan
kolom Kelas sebagai class_name; Gen 2 menyimpan Program Studi sebagai study_program.
CSV asli tidak disalin ke repository. Akun admin yang ada dipertahankan tanpa perubahan.

```powershell
php artisan pensmate:import-members "C:\data\member-gen-1.csv" "C:\data\member-gen-2.csv" --dry-run
```

Hapus --dry-run untuk menyimpan. Seluruh input divalidasi sebelum penulisan dan
impor disimpan dalam satu transaksi. import_source_key unik memakai generasi dan
nomor sumber. Impor ulang melewati record yang sudah ada, termasuk yang diarsipkan;
perubahan manual tidak ditimpa dan member tidak dihidupkan kembali secara otomatis.
Jangan memakai ulang nomor sumber untuk orang lain. Command ini untuk dua format
CSV sumber tersebut, bukan sinkronisasi atau pembaruan bebas berdasarkan nama.

Email nullable mendukung data direktori yang belum mempunyai akses login. Password
acak rahasia dibuat hanya untuk memenuhi schema users; nilainya tidak dibagikan atau
disimpan sebagai plaintext. Tanpa email, record tidak dapat login atau membuka workspace.

Hapus member memakai soft delete sekaligus menonaktifkan akun. Relasi creator,
assignee, reviewer dan pembuat referensi tetap membaca arsip untuk mempertahankan
histori. Record arsip tidak dapat dipilih untuk penugasan baru. Tidak ada hard delete,
reset database, pembuatan akun admin baru, atau perubahan .env dalam pekerjaan ini.

Migration down menolak kontraksi email ke NOT NULL jika masih ada email kosong.
Sebelum rollback, cadangkan metadata generasi/kelas/prodi dan arsip; lengkapi email
melalui proses tepercaya. Jangan menghapus member agar rollback bisa dipaksakan.

Build Vite, kompilasi Blade dan tes fungsional lulus. Pemeriksaan visual browser
untuk grid terbaru belum dilakukan karena sesi ini tidak menyediakan tool browser.

## Draf siap upload dan integrasi timeline

Panduan endpoint, komponen, carousel, konfigurasi koordinator/divisi dan aturan dua
persetujuan tersedia di [CONTENT-TIMELINE-INTEGRATION.md](CONTENT-TIMELINE-INTEGRATION.md).
Pengaturan reviewer nyata dilakukan admin melalui Edit member; data impor tidak
memiliki informasi divisi/role koordinator sehingga tidak diisi secara asumsi.

## Timeline referensi terintegrasi

Menu Timeline konten membuka /timeline. Referensi dapat dibaca dari setiap jadwal,
kemudian ditambah/diubah/dihapus melalui detail sesuai izin. Navigasi bulan, filter
status/platform, kalender desktop dan agenda mobile memakai satu sumber Content.
Tes route HTML mencakup perubahan referensi yang langsung tercermin setelah reload,
escaping judul/catatan, larangan membaca konten anggota lain, dan bulan kosong.
Rendering MySQL dan build lulus; visual browser untuk timeline baru belum diuji.

## Aktivasi akses member

Member tanpa email dapat membuat kredensial melalui link personal yang dibuat admin. Lihat [MEMBER-ACTIVATION.md](MEMBER-ACTIVATION.md). Pengisian email/password manual oleh admin tetap tersedia.
