# Aktivasi akun member

## Alur admin

1. Login sebagai admin, buka **Manage member**, lalu detail anggota.
2. Pastikan anggota aktif dan belum mempunyai email login.
3. Pilih **Buat link aktivasi**, lalu salin URL yang ditampilkan.
4. Bagikan langsung kepada anggota yang sesuai melalui kanal pribadi. Aplikasi tidak mengirim email atau pesan otomatis.

URL lengkap ditampilkan pada hasil pembuatan. Bila kehilangan URL, buat link baru; link sebelumnya otomatis dicabut. Admin juga dapat memilih **Cabut link** pada detail anggota.

## Alur member

Buka URL, isi email yang belum digunakan, lalu buat dan konfirmasi password minimal 10 karakter dengan huruf dan angka, maksimal 72 byte. Setelah berhasil, login menggunakan email dan password tersebut.

Jika URL dibuka saat masih login sebagai admin atau akun lain, gunakan jendela privat. Aktivasi tidak membuat member duplikat: akun direktori yang sama memperoleh email dan password. Nama, generasi, divisi, role, dan status tetap dikendalikan admin.

## Aturan

- Berlaku 24 jam, sekali pakai. GET untuk melihat formulir tidak menghabiskan link.
- Link yang kedaluwarsa, dicabut, atau sudah dipakai tidak dapat digunakan kembali.
- Pengarsipan, penonaktifan, atau pengisian email/password melalui admin membatalkan link tertunda.
- Akun yang sudah memiliki email tidak memakai aktivasi sebagai reset password.
- Tabel aktivasi menyimpan hash token. URL lengkap adalah akses personal; bagikan hanya kepada penerima yang benar.
- Aktivasi tidak membuktikan kepemilikan kotak masuk email. `email_verified_at` tetap kosong; verifikasi email melalui SMTP belum tersedia.
- URL dibuat berdasarkan alamat aplikasi. `localhost` hanya dapat diakses pada perangkat yang sama.

## Verifikasi

Migration `2026_11_19_000003_create_member_activation_links` sudah diuji pada MySQL. Uji transaksi mencakup pembuatan link, aktivasi pada record yang sama, dan penolakan pemakaian ulang; data uji di-rollback.

Suite aktivasi mencakup 19 test dan 148 assertion, termasuk expiry, pencabutan, pembatasan akses, validasi email/password, serta pembatasan percobaan. Pemeriksaan visual browser untuk halaman aktivasi belum dilakukan.
