# Pensmate Internal

Workspace internal untuk draf konten siap upload, referensi timeline, dan pengelolaan member.

## Stack

Laravel 12, PHP 8.2+, MySQL, Blade, Tailwind CSS 4, dan Vite. Implementasi saat ini memakai Blade; Livewire belum dipasang.

## Mulai lokal

Panduan lengkap, kebutuhan lingkungan, dan troubleshooting tersedia di [panduan setup](docs/SETUP.md).

```sh
git clone --branch feature https://github.com/Pensmate-Core-Team/internal-pensmate.git
cd internal-pensmate
composer install
npm ci
```

Salin `.env.example` menjadi `.env`, buat database MySQL kosong bernama `pensmate_internal`, lalu isi kredensial lokal. Jalankan:

```sh
php artisan key:generate
php artisan migrate
php artisan pensmate:admin
npm run build
php artisan serve
```

Buka `http://localhost:8000/login`. Pembuatan admin hanya diperlukan pada instalasi baru; gunakan akun yang ada ketika melanjutkan database tim.

## Fitur tersedia

- Draf dengan caption, jadwal, media/carousel, revisi, dan unduhan setelah dua persetujuan dari orang berbeda: admin serta koordinator divisi lain.
- Timeline bulanan dengan referensi yang terhubung ke detail konten dan dibatasi sesuai hak akses.
- CRUD member, filter generasi, arsip, grid tiga kolom pada desktop, dan aktivasi akun lewat link personal sekali pakai.
- Posting ke media sosial dilakukan manual. Notifikasi email dan posting otomatis belum tersedia.

## Pemeriksaan

```sh
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
npm run build
php artisan view:cache
```

Test memakai SQLite dalam memori melalui `tests/TestCase.php`; ekstensi `pdo_sqlite` wajib tersedia.

## Dokumentasi

- [Setup, development, dan pembaruan database](docs/SETUP.md)
- [Aktivasi akun member](docs/MEMBER-ACTIVATION.md)
- [Workspace dan impor member](docs/CONTENT-WORKSPACE.md)
- [Carousel, review, dan integrasi timeline](docs/CONTENT-TIMELINE-INTEGRATION.md)
- [Lingkup produk](PRODUCT.md) dan [panduan desain](DESIGN.md)

Repository memuat kode dan konfigurasi contoh. Database, CSV member, `.env`, kredensial, serta media unggahan tidak didistribusikan melalui Git.
