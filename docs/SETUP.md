# Setup Pensmate Internal

## Kebutuhan

- PHP 8.2 atau lebih baru dengan ekstensi Laravel, `pdo_mysql`, serta `pdo_sqlite` untuk pengujian.
- Composer 2, MySQL 8, dan Node.js 22 LTS dengan npm.
- Akses tulis aplikasi ke `storage` dan `bootstrap/cache`.

Gunakan lockfile yang disertakan: `composer install` dan `npm ci`. Jalankan `composer check-platform-reqs` untuk memeriksa kebutuhan PHP pada mesin sendiri.

## Instalasi baru

1. Clone branch `feature`, lalu install dependency sesuai README.
2. Salin konfigurasi contoh:

   PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

   macOS/Linux:

   ```sh
   cp .env.example .env
   ```

3. Buat database kosong `pensmate_internal` melalui klien MySQL. Sesuaikan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` dalam `.env`. Gunakan kredensial lokal sendiri.
4. Jalankan:

   ```sh
   php artisan key:generate
   php artisan migrate
   php artisan pensmate:admin
   npm run build
   php artisan serve
   ```

Command admin meminta nama, email, dan password lewat prompt. Tidak ada akun atau password admin bawaan. Jangan menjalankan seeder demo pada database tim.

`APP_URL` harus sama dengan alamat aplikasi yang diakses. Contoh lokal memakai `http://localhost:8000`. Driver session, cache, dan queue memakai database; migration harus selesai sebelum aplikasi dipakai.

## Development

Jalankan dua terminal:

```sh
php artisan serve
```

```sh
npm run dev
```

Vite menyediakan pembaruan CSS/JS selama development. Jika memakai hasil build, hentikan Vite dan jalankan `npm run build`.

## Memperbarui instalasi yang sudah ada

Simpan perubahan lokal sebelum mengambil pembaruan. Cadangkan database, lalu jalankan:

```sh
git pull --ff-only origin feature
composer install
npm ci
php artisan migrate
npm run build
php artisan view:clear
```

Pertahankan `.env` dan `APP_KEY` yang sudah digunakan. Tidak perlu membuat admin baru. Jangan memakai `migrate:fresh`, `migrate:refresh`, atau seed demo untuk memperbarui database berisi member.

Migration aktivasi membuat `member_activation_links` tanpa mengubah record pengguna. Rollback migration ini menolak penghapusan tabel yang masih berisi riwayat; siapkan pencadangan dan rencana pemulihan sebelum rollback.

## Pemeriksaan sebelum push

```sh
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
npm run build
php artisan view:cache
git diff --check
```

Test memakai database SQLite dalam memori. Jangan ubah pengujian agar menggunakan database tim.

## Troubleshooting

- `member_activation_links doesn't exist`: periksa `php artisan migrate:status`, lalu jalankan `php artisan migrate` pada koneksi database aplikasi.
- `Access denied` atau `Connection refused`: periksa layanan MySQL dan kredensial `.env`; jalankan `php artisan config:clear` setelah perubahan konfigurasi.
- `Vite manifest not found`: jalankan `npm ci` dan `npm run build`.
- `could not find driver`: aktifkan `pdo_mysql` untuk aplikasi atau `pdo_sqlite` untuk test pada PHP CLI yang digunakan.
- Link aktivasi tidak dapat dibuka dari ponsel: `localhost` menunjuk perangkat pembuka. Gunakan alamat aplikasi yang dapat dijangkau penerima dan sesuaikan `APP_URL` sebelum membuat link baru.

## Data dan deployment

CSV impor dan database tidak ikut repository. Gunakan dokumen impor untuk memasukkan data resmi melalui akses yang diizinkan. File draf disimpan privat; tidak perlu membuat symlink publik untuk membagikan media konten.

Setup ini untuk pengembangan lokal. Deployment memerlukan HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, kredensial server sendiri, dan document root menuju `public`. Jangan membagikan `.env`, dump database, atau link aktivasi melalui Git.
