---
name: Pensmate
description: Ruang kerja konten dengan dunia visual Meja editorial
colors:
  primary: "#246344"
  primary-hover: "#194b32"
  paper: "#f8f7f4"
  ink: "#202a24"
  muted: "#647067"
  line: "#dfe4dd"
  white: "#ffffff"
  sidebar: "#f0f2eb"
  topbar: "#fcfcf9"
  nav-active: "#dce8d3"
  nav-active-text: "#224e31"
  summary: "#e8eee1"
  field-border: "#cbd2c9"
  draft-bg: "#eef0eb"
  draft-text: "#586355"
  review-bg: "#e6eef8"
  review-text: "#325d91"
  revision-bg: "#fff0d6"
  revision-text: "#875713"
  ready-bg: "#e1efdf"
  ready-text: "#2b6336"
  published-bg: "#e2eeea"
  published-text: "#31695b"
  error: "#a43228"
  review-note: "#fbefda"
typography:
  headline:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "2rem"
    fontWeight: 600
    lineHeight: "1.25"
    letterSpacing: "-0.03em"
  title:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: "1.375"
    letterSpacing: "-0.015em"
  body:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: "1.5"
    letterSpacing: "normal"
  label:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: "1.428571"
    letterSpacing: "normal"
rounded:
  md: "6px"
  lg: "8px"
  xl: "12px"
  full: "calc(infinity * 1px)"
spacing:
  2: "8px"
  3: "12px"
  4: "16px"
  5: "20px"
  6: "24px"
  7: "28px"
  10: "40px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.white}"
    rounded: "{rounded.lg}"
    padding: "10px 16px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
  panel:
    backgroundColor: "{colors.white}"
    rounded: "{rounded.xl}"
    padding: "28px"
  field:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
---

# Design System: Pensmate

## Overview

**Creative North Star: "Meja editorial"**

Meja editorial adalah ruang kerja yang tenang, jelas, dan mudah dipindai. Kertas putih hangat dan hijau membentuk suasana kerja tim tanpa mengalihkan perhatian dari materi konten.

Mode Operate mengutamakan tugas berulang: membaca judul, mengenali status, menilai materi, dan mengambil tindakan. Sidebar menjaga orientasi; garis tipis dan bidang tonal membagi informasi. Bahasa antarmuka tetap Indonesia.

**Key Characteristics:**
- Putih hangat dengan aksen hijau yang terarah.
- Baris konten mudah dipindai dan panel detail terbaca.
- Permukaan datar, garis tipis, dan sudut lembut.
- Label status selalu menyertai warna.

## Colors

Palet terasa seperti kertas hangat dengan penanda hijau. Frontmatter memuat nilai normatif dari stylesheet.

### Primary

- **Hijau kerja** (`primary`): tombol utama, tautan tindakan, garis fokus, dan penanda navigasi.
- **Hijau pekat** (`primary-hover`): umpan balik hover tombol utama.

### Neutral

- **Kertas hangat** (`paper`): latar halaman; **putih** (`white`): panel dan bidang isian.
- **Tinta** (`ink`) untuk isi utama; **teks pendamping** (`muted`) untuk petunjuk, metadata, dan footer.
- **Garis lembut** (`line`) untuk pembatas; sidebar dan topbar menggunakan bidang netral tersendiri.
- Status memakai pasangan `draft`, `review`, `revision`, `ready`, dan `published`. Biru dan amber menandai keadaan, bukan aksen merek tambahan.
- `error` menandai pesan kesalahan; `review-note` membedakan catatan reviewer.

**The Status Label Rule.** Warna status selalu disertai teks; titik warna hanya penguat.

## Typography

**Display Font / Body Font:** Instrument Sans dideklarasikan, lalu `ui-sans-serif`, `system-ui`, dan `sans-serif`. Tidak ada berkas Instrument Sans yang dibundel; runtime saat ini memakai fallback sistem. Jangan menambahkan unduhan font tanpa kebutuhan baru.

Huruf sans memberi ritme kerja yang lugas. Judul menggunakan tracking rapat; isi dan metadata tetap netral. Tidak ada font mono khusus.

### Hierarchy

- **Headline:** judul halaman mengikuti token `headline`; pada lebar sampai 767px ukurannya 1.65rem.
- **Title:** judul panel mengikuti `title`; beberapa judul ringkasan memakai 1rem.
- **Body:** teks dasar mengikuti `body`; paragraf memakai leading-relaxed (1.625). Caption memakai 0.875rem dengan tinggi baris 1.75rem.
- **Label:** formulir 0.875rem, bobot 500; tombol bobot 600. Metadata 0.75rem.
- Angka ringkasan 3.5rem, bobot 500, tinggi baris 1, tracking -0.04em; pada ponsel 3rem. Angka jumlah dan jadwal memakai tabular numerals.
- Halaman login memiliki judul cerita 3rem dengan tinggi baris 1.15, turun menjadi 2.25rem dan 1.9rem pada breakpoint berikutnya.

## Layout

Sidebar desktop tetap di kiri (248px); konten utama memiliki offset yang sama. Topbar setinggi 76px. Workspace berpusat dengan lebar maksimum 1560px, padding horizontal 40px, atas 40px, bawah 24px. Pada lebar minimal 1600px, padding atas menjadi 48px.

Daftar konten memakai lima kolom: `minmax(200px, 2.5fr) minmax(112px, 1.1fr) minmax(100px, 1.1fr) minmax(110px, 1.1fr) 44px`. Baris minimal 108px, menjadi 116px pada layar besar. Detail dan formulir memakai kolom fleksibel serta kolom pendamping 320px, dengan celah 24px. Panel memakai padding 28px dan tumpukan formulir bercelah 24px.

- Sampai 1279px: sidebar 216px; workspace 32px 24px 24px; panel 24px; kolom pendamping 280px.
- Sampai 1023px: sidebar menjadi drawer 248px; offset utama hilang. Detail masih dua kolom. Drawer bergerak 180ms ease-out.
- Sampai 767px: detail menjadi satu kolom; workspace 28px 20px 20px; topbar 68px. Header daftar disembunyikan, setiap baris menyusun metadata ulang. Tombol judul halaman mengisi lebar. Tab status dapat digulir horizontal. Filter membungkus; pencarian mengisi satu baris.
- Login memakai dua kolom sama besar dan menjadi satu kolom sampai 767px.

## Elevation & Depth

Permukaan utama datar. Kedalaman berasal dari perbedaan warna kertas, panel putih, dan garis pembatas. Satu bayangan struktural muncul pada drawer navigasi terbuka: `12px 0 36px #1a332015`. Panel dan tombol tidak memakai bayangan.

**The Flat Workspace Rule.** Pisahkan kelompok dengan garis dan nada bidang; simpan bayangan untuk drawer yang menutup konten.

## Shapes

Sudut lembut: bidang isian, tombol, navigasi, dan media memakai 8px; panel serta wadah daftar 12px; chip status 6px. Avatar bulat. Border umum 1px. Unggahan dan media kosong memakai border putus-putus untuk menunjukkan slot materi.

## Components

### Buttons

Tombol utama tegas namun ringkas: hijau kerja, teks putih, padding 10px 16px, tinggi minimal 44px. Tombol sekunder putih dengan border `#cfd6cd` dan teks `#34453a`; hover memakai `#edf1e9`. Hover mengangkat posisi 1px; active menekan 1px. Transisi warna dan transform berlangsung 160ms. Tombol disabled memiliki opacity 0.6 dan kursor tunggu.

### Chips

Status menggunakan pasangan warna semantik di frontmatter, padding 4px 10px, radius 6px, teks 12px, dan titik 6px. Label tidak boleh hilang. Penghitung tab memakai angka tabular serta bidang netral, lalu hijau saat terpilih.

### Cards / Containers

Panel putih memiliki garis lembut, radius 12px, dan padding 28px. Wadah daftar membatasi overflow dan memisahkan baris dengan garis ringan. Ringkasan editorial memakai bidang hijau pucat serta angka besar; catatan review memakai bidang amber lembut.

### Inputs / Fields

Input putih memiliki border `field-border`, radius 8px, padding horizontal 12px, dan tinggi minimal 46px. Textarea dapat diubah tingginya secara vertikal. Fokus global berupa outline hijau 2px dengan offset 4px. Input invalid memakai border `#b23f36`; pesan kesalahan memakai token `error`. Hubungkan pesan dengan `aria-describedby`, tandai `aria-invalid`, dan pertahankan isian. Form referensi memakai error bag serta ID terpisah per referensi.

### Navigation

Item sidebar memiliki tinggi minimal 48px, radius 8px, serta padding horizontal 12px. Hover memakai `#e4e9de`; active memakai `nav-active` dan `nav-active-text`. Halaman aktif memiliki `aria-current="page"`. Drawer mobile memiliki tombol buka/tutup berlabel. Skip link menjadi terlihat saat fokus. Pemisah breadcrumb dekoratif memakai `aria-hidden="true"`.

### Content rows and review

Judul konten menjadi titik baca utama, disertai platform dan jumlah referensi. Hover judul memberi warna hijau dan garis bawah. Kolom pendamping detail menempatkan fakta publikasi, catatan review, dan tindakan berikutnya. Tautan referensi menampilkan judul, domain, dan catatan yang dapat membungkus. Preview media memakai `object-contain`, tinggi maksimum 500px.

Pengaturan `prefers-reduced-motion: reduce` mematikan transisi dan animasi. Pertahankan perilaku ini saat menambah komponen.

## Do's and Don'ts

### Do:

- Do gunakan hijau utama untuk tindakan utama dan navigasi terpilih.
- Do pertahankan judul, status, penanggung jawab, dan jadwal sebagai informasi yang mudah dipindai.
- Do tampilkan label teks pada status serta fokus keyboard yang terlihat.
- Do gunakan bahasa Indonesia untuk instruksi dan umpan balik.

### Don't:

- Don't menambah bayangan dekoratif pada panel datar.
- Don't mengandalkan warna saja untuk menyampaikan status atau kesalahan.
- Don't menyatakan Instrument Sans sudah dimuat; berkas font belum dibundel.
- Don't memaksakan tabel desktop ke layar kecil; gunakan susunan baris responsif yang sudah ada.
