# PRD Modul — Timeline Konten dan Poin Keaktifan

**Produk:** Portal Internal PENSmate\
**Turunan dari:** PRD Master — Portal Internal PENSmate v1.1 (bagian F1 dan F2)\
**Status:** Draft untuk validasi pengurus\
**Versi:** 1.2\
**Tanggal:** 16 November 2026 (v1.0), diperbarui 24 November 2026 (v1.1: menambah bagian 13; v1.2: menyesuaikan bagian 13 dengan kode modul Konten dan Manage Member)

## 1. Ringkasan dan ruang lingkup

Dokumen ini menjelaskan kebutuhan dua modul dari Portal Internal PENSmate: **Timeline Konten** dan **Poin Keaktifan**. Keduanya dipisah supaya bisa dikerjakan, diuji, dan divalidasi tersendiri tanpa harus menunggu modul lain (Absensi, Manage Member, Pekerjaan, Draf Siap Unggah).

**Dalam cakupan dokumen ini:**

- Timeline Konten: item konten, status pengerjaan, tampilan daftar/kalender/papan, filter, dan riwayat perubahan.
- Poin Keaktifan: buku besar poin, aturan poin, verifikasi, koreksi, dan tampilan saldo serta riwayat.

**Di luar cakupan (ditangani di PRD modul lain):**

- Link referensi per item timeline (F4), paket draf siap unggah (F3), pekerjaan Belum Diambil (F7), absensi QR/kode (F6), dan manajemen anggota (F5).
- Pengunggahan otomatis ke Instagram, TikTok, atau platform lain.
- Hadiah, sanksi, atau penilaian kedisiplinan berdasarkan poin (belum ada kebijakan organisasi yang disetujui).

**Titik sambung dengan modul lain** (hanya sebagai antarmuka, detailnya di PRD modul terkait):

| Modul lain | Hubungan dengan modul ini |
| --- | --- |
| Pekerjaan (F7) | Tugas yang dipecah dari item timeline; tugas yang diverifikasi selesai dapat menjadi sumber poin |
| Draf Konten (F3) | Item timeline yang disetujui masuk ke paket Siap Diunggah; status timeline ikut diperbarui |
| Link Referensi (F4) | Ditampilkan di halaman detail item timeline |
| Absensi (F6) | Kehadiran yang diverifikasi dapat menjadi sumber poin |
| Manage Member (F5) | Sumber data anggota, divisi, peran, dan status aktif |

## 2. Latar belakang dan masalah

PENSmate memiliki alur kerja konten yang melibatkan BPH, Kreatif, Produksi, dan Public Relations. Dari panel admin yang sudah ada, belum terlihat alur khusus untuk timeline konten maupun pencatatan poin kontribusi. Namun hal ini belum boleh disimpulkan pasti tidak ada; perlu divalidasi lebih dulu ke pengurus.

**Hipotesis masalah yang perlu divalidasi:**

- Rencana konten dan statusnya tersebar di chat, spreadsheet, dan penyimpanan file, sehingga status mudah tertinggal dan tenggat terlewat.
- Kontribusi anggota dicatat manual dan berulang, sehingga rekap memakan waktu dan rawan dianggap tidak adil.

PRD ini tidak menganggap alat yang dipakai saat ini maupun tingkat masalahnya sudah terkonfirmasi.

## 3. Tujuan dan bukan tujuan

### Tujuan

1. Menyediakan satu sumber informasi untuk rencana konten dan status pengerjaannya.
2. Membuat siapa pemilik, apa statusnya, dan kapan tenggatnya terlihat jelas bagi semua yang berhak melihat.
3. Mencatat kontribusi anggota secara konsisten, transparan, dapat dikoreksi, dan dapat diaudit.
4. Mengurangi pekerjaan rekap manual bagi koordinator dan BPH.

### Bukan tujuan

- Menjadi alat desain, penyimpanan aset, atau penjadwal posting otomatis.
- Membuat kompetisi antaranggota (tidak ada peringkat publik secara default).
- Menentukan hadiah atau sanksi sebelum kebijakan resmi disahkan.

## 4. Pengguna dan hak akses

Peran mengikuti rancangan awal di PRD Master dan perlu disesuaikan dengan struktur PENSmate yang sebenarnya.

| Peran | Timeline Konten | Poin Keaktifan |
| --- | --- | --- |
| Anggota | Melihat item yang boleh diakses; memperbarui item yang menjadi miliknya | Melihat saldo dan riwayat poin sendiri; mengajukan koreksi |
| Pemilik tugas / editor konten | Membuat dan mengedit item sesuai tugas; mengubah status sebelum persetujuan | Sama seperti anggota |
| Koordinator divisi | Mengelola item divisinya; meninjau dan memberi revisi | Memverifikasi aktivitas divisinya; melihat ringkasan divisi |
| BPH / Admin organisasi | Akses lintas divisi; mengatur daftar status | Menetapkan aturan poin; mengoreksi transaksi; melihat ringkasan tim |
| Admin teknis | Konfigurasi sistem; tidak otomatis membaca isi konten | Tidak mengubah poin kecuali atas permintaan tercatat |

**Prinsip akses:** hak minimum yang cukup untuk menjalankan tugas. Pengecekan izin dilakukan di server, bukan hanya disembunyikan di tampilan.

## 5. Modul Timeline Konten

### 5.1 Tujuan

Memberi seluruh tim gambaran rencana dan progres konten tanpa bergantung pada satu format kalender saja.

### 5.2 User story

| ID | Sebagai | Saya ingin | Supaya |
| --- | --- | --- | --- |
| TL-U1 | Anggota | melihat daftar konten yang sedang berjalan beserta status dan pemiliknya | saya tahu apa yang sedang dikerjakan tim |
| TL-U2 | Anggota | melihat konten yang terkait dengan saya dan tenggatnya | saya tidak melewatkan pekerjaan |
| TL-U3 | Editor konten | memperbarui status dan menaruh catatan pada item saya | progres terlihat tanpa harus lapor lewat chat |
| TL-U4 | Koordinator | memfilter timeline per divisi, kanal, status, dan penanggung jawab | saya bisa memantau progres divisi |
| TL-U5 | Koordinator | membuat item konten baru dengan brief, kanal, dan tenggat | pekerjaan bisa dipecah dan dibagikan |
| TL-U6 | BPH | melihat item yang melewati tenggat atau tidak punya pemilik | saya bisa menindaklanjuti dengan cepat |
| TL-U7 | Anggota | membuka timeline lewat ponsel | saya bisa cek statusnya kapan saja |

### 5.3 Data item konten

Setiap item konten memuat minimal:

| Kolom | Keterangan | Wajib |
| --- | --- | --- |
| Judul | Nama konten | Ya |
| Ringkasan / brief | Gambaran isi dan tujuan konten | Ya |
| Pilar / kategori | Jenis konten menurut kesepakatan PENSmate | Ya |
| Kanal tujuan | Platform tempat konten terbit (mis. Instagram, TikTok) | Ya |
| Divisi | Divisi yang bertanggung jawab | Ya |
| Pemilik | Penanggung jawab utama item | Ya sebelum masuk Produksi |
| Anggota / tugas terkait | Orang atau tugas yang terlibat | Tidak |
| Status | Posisi pada alur status | Ya |
| Tanggal target | Tenggat atau tanggal terbit yang direncanakan | Ya |
| Tautan aset / draf | Tautan ke penyimpanan eksternal (mis. Drive) | Tidak |
| Alasan | Wajib saat status Perlu Revisi atau Dibatalkan | Bersyarat |

### 5.4 Alur status

**Alur utama:** Ide → Perencanaan → Produksi → Tinjauan → Disetujui → Siap Diunggah → Terbit → Diarsipkan

**Status tambahan:** Perlu Revisi dan Dibatalkan, keduanya wajib menyertakan alasan.

| Aturan | Penjelasan |
| --- | --- |
| Siapa boleh mengubah status | Pemilik/editor untuk tahap sebelum Tinjauan; koordinator atau peran penyetuju untuk Disetujui |
| Persetujuan | Hanya peran yang ditetapkan yang dapat memindahkan item ke Disetujui |
| Perubahan setelah disetujui | Perubahan isi penting setelah Disetujui mengembalikan status ke Tinjauan |
| Status dapat diatur | Admin dapat mengubah daftar status, tetapi histori item lama tidak boleh hilang |
| Terbit | Item hanya menjadi Terbit setelah URL posting dan waktu terbit dicatat (terhubung ke F3) |

### 5.5 Tampilan dan interaksi

- **Daftar:** tampilan utama, bisa diurutkan menurut tanggal target atau status.
- **Kalender:** menampilkan item menurut tanggal target.
- **Papan status:** kartu dikelompokkan per kolom status.
- Setiap tampilan memakai filter yang sama: rentang tanggal, divisi, kanal, status, dan penanggung jawab.
- Item yang melewati tenggat dan item yang menunggu tindakan diberi penanda jelas, tidak hanya dibedakan dengan warna.
- Membuka kartu menampilkan detail, catatan pembaruan, dan riwayat perubahan penting.

### 5.6 Kebutuhan fungsional

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| TL-F1 | Pengguna berizin dapat membuat, mengedit, dan mengarsipkan item konten | MVP |
| TL-F2 | Sistem menampilkan item dalam tampilan daftar | MVP |
| TL-F3 | Sistem menampilkan item dalam tampilan kalender dan/atau papan status | MVP (minimal satu) |
| TL-F4 | Pengguna dapat memfilter dan mengurutkan item (tanggal, divisi, kanal, status, penanggung jawab) | MVP |
| TL-F5 | Setiap perubahan status menyimpan pelaku, waktu, dan alasan bila relevan | MVP |
| TL-F6 | Item melewati tenggat dan item tanpa pemilik ditandai | MVP |
| TL-F7 | Pengguna hanya melihat item sesuai hak akses perannya | MVP |
| TL-F8 | Admin dapat mengatur daftar status tanpa menghilangkan histori | Tahap berikutnya |
| TL-F9 | Pengguna dapat melihat riwayat perubahan penting pada sebuah item | MVP |
| TL-F10 | Notifikasi tenggat dan perubahan status | Peningkatan (menunggu keputusan notifikasi) |

### 5.7 Kriteria penerimaan

- Anggota dapat melihat item yang memang boleh diakses dan memahami status serta pemiliknya.
- Item dapat disaring dan diurutkan; tenggat yang lewat dan pekerjaan yang menunggu tindakan tampak jelas.
- Perubahan status penting menyimpan siapa yang mengubah dan kapan.
- Item tidak dapat berpindah ke Disetujui oleh pengguna yang tidak berwenang (diuji di sisi server).
- Timeline dapat dibuka dan digunakan pada layar ponsel tanpa fungsi utama tersembunyi.
- Mengubah daftar status oleh admin tidak menghapus riwayat status item yang sudah ada.

### 5.8 Kasus khusus yang perlu ditangani

- Dua orang mengubah status item yang sama hampir bersamaan: sistem tidak boleh menimpa perubahan secara diam-diam.
- Pemilik item menjadi nonaktif: item tetap terlihat dan ditandai butuh pemilik baru.
- Tanggal target diubah: perubahan tercatat di riwayat.
- Item dibatalkan: tidak muncul sebagai pekerjaan aktif, tetapi tetap bisa dilihat di arsip/riwayat.
- Zona waktu tampilan memakai Asia/Jakarta.

## 6. Modul Poin Keaktifan

### 6.1 Tujuan

Memberi catatan kontribusi yang konsisten dan mudah dipahami, **bukan** mengubah partisipasi menjadi kompetisi yang tidak sehat.

### 6.2 User story

| ID | Sebagai | Saya ingin | Supaya |
| --- | --- | --- | --- |
| PK-U1 | Anggota | melihat saldo dan riwayat poin saya | saya tahu kontribusi yang tercatat |
| PK-U2 | Anggota | melihat sumber setiap penambahan poin | saya paham alasan poin diberikan |
| PK-U3 | Anggota | mengajukan koreksi jika ada poin yang salah | catatan kontribusi saya akurat |
| PK-U4 | Koordinator | memverifikasi aktivitas anggota divisi saya | poin hanya diberikan untuk aktivitas yang benar-benar selesai |
| PK-U5 | BPH | menetapkan aturan aktivitas dan nilai poin | penilaian konsisten dan sesuai kebijakan |
| PK-U6 | BPH | melihat ringkasan kontribusi tim | rekap tidak perlu disusun manual |

### 6.3 Buku besar poin

Sistem menyimpan **buku besar** (ledger) poin per anggota. Setiap transaksi memuat:

| Kolom | Keterangan |
| --- | --- |
| Anggota | Penerima poin |
| Jenis | Penambahan atau pengurangan |
| Jumlah | Nilai poin pada transaksi |
| Sumber aktivitas | Aktivitas yang menjadi dasar (tugas, kegiatan, kehadiran, dsb.) |
| Tanggal | Waktu transaksi tercatat |
| Pemberi / verifikator | Orang yang memverifikasi |
| Alasan | Catatan alasan, wajib untuk koreksi |

**Aturan penting:**

- Saldo anggota selalu sama dengan jumlah seluruh transaksinya.
- Transaksi tidak dihapus atau diubah diam-diam. Koreksi dicatat sebagai **transaksi baru** dengan alasan.
- Poin tidak diberikan otomatis hanya karena membuka aplikasi atau membuat item.

### 6.4 Aturan poin

Admin berwenang dapat mengonfigurasi aturan: aktivitas apa yang bernilai poin dan berapa nilainya. Contoh aktivitas yang perlu divalidasi dengan pengurus (belum final):

- Menyelesaikan tugas konten yang sudah diverifikasi.
- Membantu pelaksanaan kegiatan.
- Hadir pada kegiatan.
- Menyelesaikan tanggung jawab dalam alur konten.

Perubahan aturan hanya berlaku untuk transaksi baru dan perubahannya tercatat.

### 6.5 Alur pemberian poin

1. Aktivitas yang termasuk dalam aturan poin selesai dikerjakan.
2. Koordinator atau verifikator berwenang memverifikasi bahwa aktivitas benar-benar selesai.
3. Sistem mencatat transaksi poin beserta sumber dan verifikatornya.
4. Anggota melihat transaksi baru pada riwayatnya.
5. Jika ada kekeliruan, anggota mengajukan koreksi; peran berizin menyetujui atau menolak dengan alasan; hasilnya dicatat sebagai transaksi baru.

### 6.6 Tampilan dan privasi

- Anggota melihat saldo dan riwayat **miliknya sendiri**, beserta aturan poin yang berlaku.
- Peran berizin melihat ringkasan tim atau divisi.
- **Tanpa peringkat publik** dan tanpa konsekuensi atau hadiah otomatis selama kebijakan belum disahkan.
- Selama kebijakan belum disahkan, fitur ditampilkan sebagai **rekap kontribusi**, bukan penilaian.

### 6.7 Kebutuhan fungsional

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| PK-F1 | Sistem menyimpan transaksi poin dalam buku besar per anggota | Tahap berikutnya |
| PK-F2 | Admin berwenang dapat mengatur aktivitas dan nilai poin | Tahap berikutnya |
| PK-F3 | Poin hanya dicatat setelah aktivitas diverifikasi | Tahap berikutnya |
| PK-F4 | Anggota melihat saldo dan riwayat sendiri | Tahap berikutnya |
| PK-F5 | Anggota dapat mengajukan koreksi | Tahap berikutnya |
| PK-F6 | Koreksi dicatat sebagai transaksi baru dengan alasan, bukan menghapus transaksi lama | Tahap berikutnya |
| PK-F7 | Peran berizin melihat ringkasan kontribusi tim/divisi | Tahap berikutnya |
| PK-F8 | Seluruh perubahan aturan dan koreksi masuk log audit | Tahap berikutnya |
| PK-F9 | Ekspor rekap poin terkontrol | Peningkatan |

### 6.8 Kriteria penerimaan

- Setiap transaksi dapat ditelusuri ke aktivitas sumber dan verifikatornya.
- Anggota dapat mengajukan koreksi atas transaksinya.
- Hanya peran berizin yang dapat menetapkan aturan atau mengoreksi poin.
- Total saldo selalu sesuai dengan seluruh transaksi yang tercatat.
- Koreksi tidak menghapus transaksi lama; keduanya terlihat di riwayat.
- Anggota tidak dapat melihat saldo atau riwayat anggota lain.

### 6.9 Kasus khusus yang perlu ditangani

- Aktivitas yang sama diverifikasi dua kali: sistem mencegah poin ganda untuk aktivitas yang sama.
- Anggota menjadi nonaktif: riwayat poin tetap tersimpan untuk rekap.
- Pindah divisi: catatan lama tidak berubah.
- Aturan poin berubah di tengah periode: transaksi lama tidak dihitung ulang kecuali lewat koreksi tercatat.

## 7. Alur lintas dua modul

1. Koordinator membuat item di Timeline dengan brief, kanal, dan tenggat.
2. Item dikerjakan sampai Disetujui dan Terbit (status Timeline ikut diperbarui).
3. Tugas terkait item diverifikasi selesai oleh koordinator.
4. Jika aktivitas tersebut termasuk dalam aturan poin, transaksi poin dicatat untuk anggota yang mengerjakan.
5. Anggota melihat poinnya di halaman Poin Keaktifan beserta sumbernya.

Untuk MVP, Timeline Konten berjalan lebih dulu tanpa bergantung pada Poin Keaktifan. Poin dihubungkan ke tugas setelah aturan disepakati.

## 8. Kebutuhan nonfungsional

- **Mobile-first:** fungsi utama nyaman dipakai di ponsel dan desktop; status pemuatan dan kesalahan jelas pada jaringan lambat.
- **Keamanan:** autentikasi anggota, kontrol akses peran di server, HTTPS, dan sesi yang kedaluwarsa di server.
- **Privasi:** modul tidak dapat diakses atau diindeks tanpa login; data pribadi diminimalkan; saldo poin tidak terbuka ke anggota lain secara default.
- **Auditabilitas:** perubahan status, persetujuan, aturan poin, dan koreksi poin menyimpan waktu, pelaku, dan alasan bila relevan.
- **Aksesibilitas:** sasaran WCAG 2.2 Level AA (operasi keyboard, label form jelas, status tidak hanya dibedakan dengan warna).
- **Keandalan:** pencadangan berkala; pesan kesalahan tidak menghilangkan input pengguna.
- **Zona waktu:** tampilan dan catatan waktu memakai Asia/Jakarta, penyimpanan timestamp konsisten.

## 9. Metrik keberhasilan

Baseline ditetapkan saat pilot; sasaran angka final belum ditetapkan.

**Timeline Konten:**

- Persentase item aktif yang memiliki pemilik, status, dan tenggat terisi.
- Jumlah item yang melewati tenggat tanpa pembaruan status.
- Waktu median dari draf dikirim sampai ditinjau.
- Tingkat penggunaan mingguan oleh anggota aktif.

**Poin Keaktifan:**

- Persentase aktivitas selesai yang memiliki jejak verifikasi.
- Jumlah dan persentase pengajuan koreksi, serta waktu penyelesaiannya.
- Waktu yang dibutuhkan untuk menyusun rekap kontribusi dibanding cara manual.
- Umpan balik anggota tentang keadilan dan kejelasan aturan poin.

## 10. Rilis yang disarankan

| Tahap | Timeline Konten | Poin Keaktifan |
| --- | --- | --- |
| Tahap 0: Validasi | Sepakati alur status, peran penyetuju, kategori/pilar, dan daftar kanal | Sepakati aktivitas bernilai poin, nilai, verifikator, dan apakah poin hanya rekap |
| MVP | Item konten, status, tampilan daftar + satu tampilan lain, filter, riwayat perubahan; uji coba satu siklus konten/divisi | Belum dirilis |
| Tahap berikutnya | Pengaturan status oleh admin | Buku besar poin, aturan, verifikasi, koreksi, tampilan saldo, sebagai rekap kontribusi |
| Peningkatan | Notifikasi | Ekspor terkontrol dan laporan ringkasan |

## 11. Risiko dan mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Anggota tidak memperbarui status sehingga timeline cepat usang | Buat pembaruan status sederhana; tampilkan item tanpa pemilik/tenggat; tetapkan pemilik operasional |
| Terlalu banyak kolom membuat pembuatan item terasa berat | Wajibkan hanya kolom minimum; sisanya opsional |
| Sistem poin terasa tidak adil atau memicu kompetisi | Aturan transparan, verifikasi, histori koreksi, tanpa peringkat/hukuman default; evaluasi aturan bersama anggota |
| Poin dianggap sebagai nilai resmi sebelum kebijakan ada | Tampilkan sebagai rekap kontribusi; tidak ada konsekuensi otomatis |
| Saldo atau data kontribusi terlihat oleh orang yang tidak berhak | Batasi tampilan per peran, uji izin di sisi server sebelum rilis |
| Poin ganda atau salah catat | Cegah duplikasi per aktivitas; koreksi sebagai transaksi baru; audit |

## 12. Keputusan yang harus disepakati

**Timeline Konten**

1. Daftar status final dan siapa yang berhak menyetujui item.
2. Daftar pilar/kategori konten dan kanal yang dipakai PENSmate.
3. Tampilan mana yang diprioritaskan untuk MVP: kalender, papan status, atau daftar saja.
4. Apakah semua anggota boleh melihat item semua divisi, atau hanya divisinya.
5. Notifikasi: dalam aplikasi saja atau juga email/chat.

**Poin Keaktifan**

1. Aktivitas apa yang bernilai poin dan berapa nilainya.
2. Siapa yang memverifikasi aktivitas dan mengoreksi poin.
3. Apakah poin hanya rekap internal atau akan terkait apresiasi tertentu.
4. Apakah saldo hanya terlihat oleh anggota sendiri atau juga oleh seluruh tim.
5. Apakah ada periode poin (per semester, per kepengurusan) atau saldo kumulatif.
6. Bagaimana alur pengajuan koreksi dan batas waktunya.

## 13. Keputusan yang sudah diambil

Disepakati tim pengembang pada 24 November 2026. Versi 1.2 menyesuaikan keputusan dengan implementasi modul Konten dan Manage Member yang sudah ada di branch `feature` (model `Content`, tabel `contents`). Keputusan bertanda **(validasi Tahap 0)** tetap perlu dikonfirmasi ke pengurus. Bila ada pertentangan dengan bagian sebelumnya, bagian ini yang berlaku.

### 13.0 Dasar implementasi

- **Item timeline adalah record `contents`.** Timeline Konten tidak membuat tabel item sendiri; ia memperluas model `Content` yang dipakai juga oleh Draf Siap Unggah (F3) dan Link Referensi (F4).
- Antarmuka teknis dengan modul tersebut diatur dalam `docs/kontrak-integrasi-F1-F2.md`.

### 13.1 Timeline Konten

| ID | Topik | Keputusan | Mengubah / menjelaskan |
| --- | --- | --- | --- |
| K-TL1 | Alur status | Memakai 5 status yang sudah ada: **Draf** (`draft`) → **Dalam review** (`in_review`) → **Perlu revisi** (`revision`) atau **Siap upload** (`ready`) → **Terunggah** (`published`). Status Ide, Perencanaan, Produksi, Disetujui (terpisah), Diarsipkan, dan Dibatalkan **tidak dibuat**. Pemetaan ke PRD: Tinjauan = Dalam review; Disetujui dan Siap Diunggah digabung menjadi Siap upload; Terbit = Terunggah. **(validasi Tahap 0)** | 5.4, 5.6 (TL-F1, TL-F8), 5.8 |
| K-TL2 | Hak transisi | **Ajukan review** (draft/revision → in_review): pembuat, PIC, atau admin; caption, jadwal, divisi, dan media wajib lengkap. **Setujui / minta revisi**: admin dan koordinator dari **divisi lain** yang bukan pembuat/PIC. Status menjadi Siap upload hanya setelah dua persetujuan (admin + koordinator divisi lain, dua orang berbeda) pada versi yang sama. Permintaan revisi wajib berisi catatan. **Tandai terunggah** (ready → published): pembuat, PIC, atau admin, wajib URL posting; waktu terbit tercatat otomatis. Semua dicek di server. | 4, 5.4 |
| K-TL3 | Perubahan setelah disetujui | Konten **terkunci** selama Dalam review dan setelah Siap upload; mengedit hanya mungkin pada Draf dan Perlu revisi. Setiap pengajuan ulang menaikkan nomor revisi dan persetujuan lama tidak berlaku. Aturan "isi penting mengembalikan status ke Tinjauan" tidak diperlukan. | 5.4 |
| K-TL4 | Penanda butuh PIC | Setiap konten wajib punya PIC (`assigned_to`) sejak dibuat. Penanda "butuh PIC baru" muncul bila PIC nonaktif atau sudah diarsipkan, dan status belum Terunggah. | 5.3, 5.6 (TL-F6), 5.8 |
| K-TL5 | Penanda lewat tenggat | Konten ditandai lewat tenggat bila jadwal (`scheduled_at`) sudah lewat dan status belum Terunggah. Penanda berupa teks/ikon, tidak hanya warna. | 5.5, 5.6 (TL-F6) |
| K-TL6 | Visibilitas | Semua anggota aktif yang punya akses login dapat **melihat semua konten**, termasuk detail, caption, dan pratinjau media. Hak mengedit, mereview, menandai terunggah, dan mengunduh media final tetap mengikuti aturan K-TL2 dan K-TL3. Perubahan ini memodifikasi `Content::visibleTo` dan `ContentPolicy::view` milik modul Konten, sehingga dikerjakan bersama pengembangnya. **(validasi Tahap 0)** | 4, 5.6 (TL-F7), 12 (Timeline nomor 4) |
| K-TL7 | Riwayat perubahan | Ditambahkan riwayat untuk perubahan status, jadwal, dan PIC (pelaku, waktu, nilai lama, nilai baru, catatan). Riwayat keputusan review yang sudah ada (`content_reviews`) tetap dipakai. | 5.6 (TL-F5, TL-F9), 5.8 |
| K-TL8 | Konten tidak dilanjutkan | Karena tidak ada status Dibatalkan/Diarsipkan, konten yang tidak dilanjutkan tetap berstatus terakhirnya. Kebutuhan pembatalan dibahas ulang bila muncul saat pilot. | 5.6 (TL-F1), 5.8 |

### 13.2 Peran dan data anggota

| ID | Topik | Keputusan | Mengubah / menjelaskan |
| --- | --- | --- | --- |
| K-R1 | Pemetaan peran | `admin` = BPH / Admin organisasi. `coordinator` = Koordinator divisi. `member` = Anggota. "Pemilik tugas / editor konten" bukan peran global, melainkan pembuat atau PIC konten. | 4 |
| K-R2 | Admin teknis | Ditunda sampai ada kebutuhan nyata. Pembatasan akses dan log akses admin teknis (sebelumnya K-X1) ikut ditunda. | 4, 8 |
| K-R3 | Data anggota | Memakai data Manage Member yang sudah ada: `users.role`, `users.division` (teks, dinormalisasi spasi, dibandingkan tanpa membedakan huruf besar/kecil), `users.is_active`, dan arsip (*soft delete*). Anggota yang diarsipkan tetap terbaca di riwayat. | 1, 4, 5.8, 6.9 |

### 13.3 Poin Keaktifan

| ID | Topik | Keputusan | Mengubah / menjelaskan |
| --- | --- | --- | --- |
| K-PK1 | Asal pengurangan poin | Pengurangan bisa berasal dari (a) **koreksi yang disetujui**, yang tertaut ke transaksi asal, atau (b) **penyesuaian manual oleh admin (BPH)**. Keduanya wajib alasan dan masuk log audit. Di tampilan, penyesuaian manual diberi label "penyesuaian", **bukan sanksi**, sesuai prinsip tanpa sanksi sebelum kebijakan disahkan (bagian 1 dan 6.6). **(validasi Tahap 0)** | 6.3, 6.5 |
| K-PK2 | Pengajuan koreksi | Pengajuan koreksi disimpan sebagai entitas tersendiri: transaksi yang dipersoalkan, alasan pengaju, status (diajukan / disetujui / ditolak), peninjau, waktu, dan alasan keputusan. Bila disetujui, hasilnya dicatat sebagai transaksi baru. | 6.5, 6.7 (PK-F5, PK-F6) |
| K-PK3 | Sumber poin dan anti-duplikasi | Setiap transaksi mereferensikan sumbernya secara generik (jenis sumber + ID sumber, mis. konten, tugas F7, atau kehadiran F6). Sistem menolak transaksi kedua untuk kombinasi anggota, sumber, dan aturan yang sama. | 6.3, 6.9 |
| K-PK4 | Divisi pada transaksi | Setiap transaksi menyimpan salinan divisi anggota saat dicatat, supaya catatan lama tidak berubah ketika anggota pindah divisi. | 6.9 |

### 13.4 Riwayat revisi bagian ini

- **v1.1 (24 November 2026):** keputusan awal, termasuk alur 10 status, penyetuju koordinator divisi sendiri, visibilitas ringkasan, dan log akses admin teknis.
- **v1.2 (24 November 2026):** disesuaikan dengan kode modul Konten dan Manage Member di branch `feature`: alur 5 status, dua persetujuan lintas divisi, visibilitas penuh, admin = BPH, dan admin teknis ditunda.

## 14. Referensi

- PRD Master — Portal Internal PENSmate v1.1 (F1 Timeline Konten dan F2 Poin Keaktifan, serta bagian peran, nonfungsional, dan risiko).
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html) untuk pengelolaan sesi.
- [W3C WCAG 2.2 Quick Reference](https://www.w3.org/WAI/WCAG22/quickref/) untuk aksesibilitas.
