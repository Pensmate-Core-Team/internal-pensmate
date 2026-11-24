# Timeline Konten MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan riwayat perubahan konten, penanda "Lewat tenggat"/"Butuh PIC", tampilan Daftar dan Papan, serta filter bersama di atas modul Konten yang sudah ada.

**Architecture:** Semua data tetap di `contents`. Riwayat diisi lewat observer Eloquent, jadi `TransitionContent` dan `SaveContent` tidak diubah. Filter dipusatkan di satu kelas `App\Support\TimelineFilters` yang dipakai kalender (sudah ada), `/timeline/list`, dan `/timeline/board`. Ketiganya tetap memakai `Content::visibleTo`. Tampilan memakai komponen Blade bersama untuk tab, form filter, dan badge penanda.

**Tech Stack:** Laravel 12, PHP 8.2+ (lokal 8.5), Blade, Tailwind CSS 4, Vite, PHPUnit 11, SQLite in-memory untuk tes.

**Spec:** `docs/superpowers/specs/2026-11-24-timeline-mvp-design.md`. Baca juga PRD v1.2 bagian 13 dan `docs/kontrak-integrasi-F1-F2.md`.

## Global Constraints

- **Perintah tes** (PHP lokal tidak memuat pdo_sqlite secara default; `artisan test` tidak meneruskan `-d`): `php -d extension=pdo_sqlite vendor/bin/phpunit`. Kondisi awal: **79 tes, 557 assertion, semuanya lulus**.
- Semua query konten untuk pengguna harus lewat `Content::visibleTo($user)`. Scope ini tidak boleh diubah di rencana ini.
- Jangan ubah `TransitionContent`, `SaveContent`, `ContentPolicy`, `TimelineContentController` (feed JSON), atau tes yang sudah ada.
- Zona waktu `config('app.timezone')` = Asia/Jakarta. Teks jam selalu diberi akhiran "WIB".
- Antarmuka berbahasa Indonesia. Teks tetap: "Lewat tenggat", "Butuh PIC", "Kalender", "Daftar", "Papan", "Riwayat perubahan", "Belum ada perubahan tercatat", "Menampilkan yang terunggah 30 hari terakhir", "Lihat semua di Daftar", "(nonaktif)", "(belum aktivasi)", "Belum dijadwalkan", "Sistem".
- Halaman harus berfungsi tanpa JavaScript. Penanda tidak boleh hanya berupa warna. Di lebar 320px tidak boleh ada gulir menyamping pada halaman.
- Ikuti `DESIGN.md` dan kelas CSS yang sudah ada. Kelas baru ditambahkan di `resources/css/app.css` dengan kontras teks minimal 4.5:1.
- Respons halaman timeline memakai header `Cache-Control: private, no-store`.
- Pola fixture tes mengikuti `tests/Feature/TimelinePageTest.php`: `RefreshDatabase`, `withoutVite()`, `User::factory()->create(['role'=>..., 'division'=>..., 'is_active'=>true])`, dan `Content` dibuat dengan `forceFill(['created_by'=>..., 'status'=>...])`.
- Setiap commit diakhiri baris `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Format kode file baru dengan `vendor/bin/pint <file>`. Jangan menjalankan pint pada file milik dev lain secara keseluruhan.

## Review Focus

1. **Divisi dengan ejaan berbeda** ("kreatif ", "KREATIF", "Kreatif"). Harus dianggap satu divisi, baik di dropdown maupun filter. Tes ada di Task 4.
2. **Konten tanpa jadwal** (`scheduled_at` null). Di Daftar dan Papan tampil paling akhir dengan teks "Belum dijadwalkan". Konten ini tidak ikut bila `from`/`to` diisi, dan tidak pernah dianggap "Lewat tenggat". Tes ada di Task 3 dan Task 6.
3. **PIC atau aktor yang diarsipkan (soft delete).** Nama tetap tampil di riwayat, dropdown, daftar, dan papan tanpa error. Tes ada di Task 2 dan Task 4.
4. **Parameter query tidak valid** (`from` > `to`, `flag=xyz`, `assignee=abc`, `sort=foo`, `division` lebih dari 120 karakter). Harus menghasilkan error validasi (redirect 302 dengan pesan), bukan error 500. Tes ada di Task 6.
5. **Batas hari di WIB.** Konten pada `2026-11-30 23:30 WIB` ikut terfilter bila `to=2026-11-30`, dan konten pada `2026-11-01 00:00 WIB` ikut bila `from=2026-11-01`. Tes ada di Task 4.

---

## File Structure

| File | Status | Tanggung jawab |
| --- | --- | --- |
| `database/migrations/2026_11_24_000001_create_content_histories_table.php` | baru | Tabel riwayat |
| `app/Models/ContentHistory.php` | baru | Model riwayat dan label tampilan |
| `app/Observers/ContentHistoryObserver.php` | baru | Menulis riwayat pada event `created`/`updated` |
| `app/Models/Content.php` | ubah | Relasi `histories()`, atribut `#[ObservedBy]`, method dan scope penanda |
| `resources/views/components/content-history.blade.php` | baru | Panel riwayat |
| `resources/views/components/content-flags.blade.php` | baru | Badge penanda |
| `resources/views/contents/show.blade.php` | ubah | Menyisipkan badge dan panel riwayat |
| `app/Support/TimelineFilters.php` | baru | Validasi, penerapan, dan opsi filter |
| `resources/views/components/timeline-tabs.blade.php` | baru | Tab Kalender/Daftar/Papan |
| `resources/views/components/timeline-filter-form.blade.php` | baru | Form filter bersama |
| `app/Http/Controllers/TimelineController.php` | ubah | Kalender memakai `TimelineFilters` |
| `resources/views/timeline/index.blade.php` | ubah | Tab, form bersama, dan badge di kartu |
| `app/Http/Controllers/TimelineListController.php` | baru | `/timeline/list` |
| `resources/views/timeline/list.blade.php` | baru | Tampilan daftar |
| `app/Http/Controllers/TimelineBoardController.php` | baru | `/timeline/board` |
| `resources/views/timeline/board.blade.php` | baru | Tampilan papan |
| `routes/web.php` | ubah | Dua route baru |
| `resources/css/app.css` | ubah | Gaya badge, tab, dan papan |
| `tests/Feature/ContentHistoryTest.php`, `ContentFlagsTest.php`, `TimelineFiltersTest.php`, `TimelineCalendarFiltersTest.php`, `TimelineListTest.php`, `TimelineBoardTest.php` | baru | Tes |

---

### Task 1: Tabel dan observer riwayat perubahan

**Files:**
- Create: `database/migrations/2026_11_24_000001_create_content_histories_table.php`, `app/Models/ContentHistory.php`, `app/Observers/ContentHistoryObserver.php`
- Modify: `app/Models/Content.php`
- Test: `tests/Feature/ContentHistoryTest.php`

**Interfaces:**
- Produces:
  - Model `App\Models\ContentHistory`: `$timestamps = false`, `created_at` di-cast ke `datetime`, `content(): BelongsTo`, `actor(): BelongsTo` (ke `User`, `withTrashed()`).
  - `Content::histories(): HasMany`, diurutkan `latest('id')`.
  - Konstanta `ContentHistoryObserver::FIELDS = ['status', 'scheduled_at', 'assigned_to']`.
  - Kolom tabel sesuai spec 5.1: `content_id`, `actor_id` nullable, `field` string(32), `old_value`/`new_value` string nullable, `note` text nullable, `created_at`, indeks `(content_id, created_at)`.

- [ ] **Step 1: Tulis tes yang gagal** di `tests/Feature/ContentHistoryTest.php`

  Pakai helper fixture yang sama dengan `TimelinePageTest`. Untuk transisi, panggil route HTTP `contents.transition` dengan `actingAs`, supaya aktor dari `auth()` sama seperti di produksi. Media cukup dibuat dengan `Storage::fake('local')`, lalu tulis file dan buat `ContentAsset` (lihat `tests/Feature/ContentDeliveryTest.php` untuk pola pengajuan review).

  ```php
  test_creating_content_records_initial_status()
      // actingAs($owner); simpan konten baru lewat POST route('contents.store') minimal (title, platform, assigned_to)
      // assert 1 baris: field 'status', old_value null, new_value 'draft', actor_id = owner->id
  test_full_workflow_records_each_status_change_with_actor_and_revision_note()
      // submit oleh owner -> in_review; revise oleh admin dengan review_notes 'Perbaiki warna' -> revision;
      // submit lagi; approve admin + approve koordinator divisi lain -> ready; publish -> published
      // assert urutan (old,new,actor): (draft,in_review,owner) (in_review,revision,admin, note 'Perbaiki warna')
      //   (revision,in_review,owner) (in_review,ready,coordinator) (ready,published,owner)
      // approve pertama yang tetap in_review TIDAK menambah baris status
  test_schedule_and_assignee_changes_are_recorded()
      // admin PUT contents.update mengubah scheduled_at '2026-11-20 09:00' -> '2026-11-21 10:30' dan assigned_to A -> B
      // assert baris scheduled_at: old '2026-11-20T09:00:00+07:00', new '2026-11-21T10:30:00+07:00'
      // assert baris assigned_to: old (string) A->id, new (string) B->id
  test_untracked_field_changes_are_not_recorded()
      // ubah title & caption saja -> jumlah baris riwayat tidak bertambah
  test_failed_transaction_leaves_no_history()
      // DB::transaction(fn () => { $content->status='in_review'; $content->save(); throw new RuntimeException; }) dalam try/catch
      // assert tidak ada baris field 'status' dengan new_value 'in_review'
  test_change_without_logged_in_user_has_null_actor()
      // tanpa actingAs: $content->forceFill(['status'=>'revision'])->save() -> actor_id null
  ```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

  Run: `php -d extension=pdo_sqlite vendor/bin/phpunit --filter ContentHistoryTest`
  Expected: FAIL (tabel `content_histories` tidak ada).

- [ ] **Step 3: Implementasi**

  1. **Migrasi.** `restrictOnDelete` untuk kedua FK. Tidak ada `updated_at`. Bagian `down()` menolak (lempar `RuntimeException`) bila tabel masih berisi data, mengikuti pola migrasi dev lain.
  2. **Model `ContentHistory`.**
  3. **Di `Content`:** tambahkan `#[ObservedBy(ContentHistoryObserver::class)]` pada kelas dan relasi `histories()`.
  4. **`ContentHistoryObserver::created(Content $content): void`:** satu baris status.
  5. **`ContentHistoryObserver::updated(Content $content): void`:** untuk setiap field di `FIELDS` yang `wasChanged()`, tulis satu baris.
     - Nilai lama: `getOriginal($field)`, yang di dalam event `updated` masih berisi nilai sebelum disimpan.
     - Serialisasi `scheduled_at`: `?->copy()->timezone(config('app.timezone'))->toIso8601String()`.
     - Serialisasi `assigned_to`: `(string)` id.
     - `note` = `$content->review_notes` hanya bila field `status` dan nilai barunya `revision`.
     - `actor_id` = `auth()->id()`.
     - `created_at` = `now()`.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

  Run: `php -d extension=pdo_sqlite vendor/bin/phpunit --filter ContentHistoryTest`, lalu jalankan seluruh suite.
  Expected: PASS. Seluruh suite: 85 tes, tidak ada yang gagal.

- [ ] **Step 5: Commit**

  ```bash
  vendor/bin/pint app/Models/ContentHistory.php app/Observers/ContentHistoryObserver.php database/migrations/2026_11_24_000001_create_content_histories_table.php tests/Feature/ContentHistoryTest.php
  git add -A app database tests && git commit -m "feat(timeline): catat riwayat status, jadwal, dan PIC konten"
  ```

### Task 2: Panel riwayat di detail konten

**Files:**
- Create: `resources/views/components/content-history.blade.php`
- Modify: `app/Models/ContentHistory.php`, `resources/views/contents/show.blade.php` (setelah panel "Riwayat review"), `app/Http/Controllers/ContentController.php::show` (tambahkan `'histories.actor'` ke `load`)
- Test: `tests/Feature/ContentHistoryTest.php`

**Interfaces:**
- Consumes: `Content::histories()` dan `ContentHistory::actor()` dari Task 1.
- Produces:
  - `ContentHistory::describe(): string`, mengembalikan kalimat tanpa nama aktor.
  - `<x-content-history :content="$content" />`.

- [ ] **Step 1: Tulis tes yang gagal**

  ```php
  test_detail_page_shows_history_newest_first_with_readable_values()
      // riwayat: status draft->in_review oleh "Sinta"; scheduled_at; assigned_to A->B (B bernama "Bima")
      // GET contents.show sebagai owner: assertSee('Riwayat perubahan'),
      // assertSeeInOrder([teks baris terbaru, teks baris terlama]),
      // assertSee('Sinta mengubah status Draf → Dalam review'), assertSee('20 Nov 2026 09:00 WIB'), assertSee('Bima')
  test_archived_actor_and_assignee_names_still_render()
      // aktor & PIC B di-soft-delete -> halaman 200, nama tetap tampil
  test_history_without_actor_is_labelled_sistem()
  test_empty_history_shows_honest_message()
      // konten lama tanpa baris riwayat (hapus baris setelah create) -> assertSee('Belum ada perubahan tercatat')
  ```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

  Run: `php -d extension=pdo_sqlite vendor/bin/phpunit --filter ContentHistoryTest`
  Expected: 4 tes baru FAIL.

- [ ] **Step 3: Implementasi**

  `describe()` menghasilkan kalimat berikut:
  - `status`: `mengubah status {label lama} → {label baru}` memakai `Content::STATUSES`. Bila nilai lama null: `membuat konten dengan status {label}`.
  - `scheduled_at`: `mengubah jadwal {lama} → {baru}`. Format `d M Y H:i` + ` WIB` dengan locale `id`, dan "Belum dijadwalkan" bila null.
  - `assigned_to`: `mengganti PIC {nama lama} → {nama baru}`. Cari nama dengan `User::withTrashed()->find()`, dan pakai "Anggota tidak ditemukan" bila tidak ada.

  Komponen menampilkan nama aktor (atau "Sistem"), lalu `describe()`, lalu `created_at` WIB, lalu `note` (`whitespace-pre-wrap`) bila ada. Gunakan markup `<section class="panel"><h2>…</h2><ol class="review-history">` seperti panel review.

- [ ] **Step 4: Jalankan tes, pastikan lulus.** Jalankan juga seluruh suite.

- [ ] **Step 5: Commit**

  `feat(timeline): tampilkan riwayat perubahan di detail konten`

### Task 3: Penanda Lewat tenggat dan Butuh PIC

**Files:**
- Create: `resources/views/components/content-flags.blade.php`
- Modify: `app/Models/Content.php`, `resources/views/contents/show.blade.php` (di sebelah `x-status` pada heading "Materi konten"), `resources/css/app.css`
- Test: `tests/Feature/ContentFlagsTest.php`

**Interfaces:**
- Produces:
  - `Content::isOverdue(): bool`
  - `Content::needsAssignee(): bool` (memakai relasi `assignee` yang sudah dimuat)
  - `Content::scopeOverdue(Builder $query): Builder`
  - `Content::scopeNeedsAssignee(Builder $query): Builder`
  - `<x-content-flags :content="$content" :compact="false" />`, merender `<span class="flag flag-overdue">` dan/atau `<span class="flag flag-needs-assignee">`

- [ ] **Step 1: Tulis tes yang gagal**

  ```php
  // travelTo('2026-11-24 12:00:00', 'Asia/Jakarta')
  test_overdue_only_for_past_schedule_and_not_published()
      // 11:59 hari ini draft -> true; 12:01 -> false; null -> false; kemarin published -> false; kemarin ready -> true
      // Content::overdue()->pluck('id') sama persis dengan konten yang isOverdue()
  test_needs_assignee_for_inactive_archived_or_unactivated_pic()
      // PIC aktif+email -> false; is_active=false -> true; soft-deleted -> true; email null -> true;
      // PIC email null tapi status published -> false
      // Content::needsAssignee()->pluck('id') sama dengan method
  test_flags_render_as_text_not_only_color()
      // render komponen (Blade::render atau via contents.show) -> assertSee('Lewat tenggat'), assertSee('Butuh PIC')
      // compact: teks pendek 'Telat' / 'PIC?' dengan aria-label & title berisi teks lengkap
  test_detail_page_shows_flags()
  ```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

  Run: `php -d extension=pdo_sqlite vendor/bin/phpunit --filter ContentFlagsTest`
  Expected: FAIL.

- [ ] **Step 3: Implementasi**

  - `scopeOverdue`: `whereNotNull('scheduled_at')->where('scheduled_at', '<', now())->where('status', '<>', 'published')`.
  - `scopeNeedsAssignee`: `where('status', '<>', 'published')->whereHas('assignee', fn ($q) => $q->where(fn ($q) => $q->where('is_active', false)->orWhereNotNull('deleted_at')->orWhereNull('email')->orWhere('email', '')))`. Relasi `assignee` sudah memakai `withTrashed()`. Pastikan `whereHas` tidak menyaring baris yang sudah diarsipkan, dan buktikan lewat tes.
  - **CSS** `.flag`, `.flag-overdue`, `.flag-needs-assignee`: badge kecil berisi ikon dan teks, dengan bentuk seperti `.status`. Warna overdue berbasis `--error` (#a43228) di atas latar terang. Butuh PIC memakai nada amber yang berbeda dari `status-revision`. Kontras teks minimal 4.5:1.
  - **Ikon:** pakai `x-icon` yang sudah ada, `calendar` untuk overdue dan `users` untuk PIC.

- [ ] **Step 4: Jalankan tes, pastikan lulus.** Jalankan juga seluruh suite.

- [ ] **Step 5: Commit**

  `feat(timeline): penanda lewat tenggat dan butuh PIC`

### Task 4: Kelas filter bersama

**Files:**
- Create: `app/Support/TimelineFilters.php`
- Test: `tests/Feature/TimelineFiltersTest.php`

**Interfaces:**
- Consumes: `Content::scopeOverdue`, `Content::scopeNeedsAssignee` (Task 3), `User::divisionKey()` (sudah ada).
- Produces:
  - `TimelineFilters::fromRequest(Request $request, bool $withRange): self`. Memvalidasi lewat `$request->validate()` sehingga input tidak valid menghasilkan `ValidationException`.
  - `TimelineFilters::apply(Builder $query, array $except = []): Builder`. `$except` berisi kunci yang dilewati, misalnya `['status']` untuk papan atau `['flag']` untuk ringkasan.
  - `TimelineFilters::get(string $key): mixed`
  - `TimelineFilters::hasRange(): bool` (`from` atau `to` terisi)
  - `TimelineFilters::carry(): array`. Isinya `status`, `platform`, `division`, `assignee`, `flag` yang terisi, untuk tautan tab.
  - `TimelineFilters::divisionOptions(User $user): Collection`, berupa map `divisionKey => label` dari konten `visibleTo($user)`, label diambil dari ejaan pertama berdasarkan urutan alfabet, dan diurutkan menurut label.
  - `TimelineFilters::assigneeOptions(User $user): Collection<int, array{id:int, label:string}>`. Isinya PIC dari konten `visibleTo($user)`, diambil `withTrashed`, diurutkan menurut nama. Label diberi akhiran " (nonaktif)" bila nonaktif atau diarsipkan, atau " (belum aktivasi)" bila email kosong.
  - Konstanta `TimelineFilters::FLAGS = ['overdue', 'needs_assignee']`.

- [ ] **Step 1: Tulis tes yang gagal**

  ```php
  test_validation_rules()
      // from=2026-11-10&to=2026-11-01 -> ValidationException kunci 'to'; flag=xyz -> 'flag'; assignee=abc -> 'assignee';
      // status=unknown -> 'status'; division 121 karakter -> 'division'
      // withRange=false: from/to diabaikan (tidak divalidasi, hasRange() false)
  test_division_filter_ignores_case_and_whitespace()
      // konten division 'Kreatif', 'kreatif', 'Produksi' -> division=' KREATIF ' -> 2 konten
  test_range_is_inclusive_in_wib_and_excludes_unscheduled()
      // 2026-11-01 00:00 WIB, 2026-11-30 23:30 WIB, 2026-12-01 00:00 WIB, null
      // from=2026-11-01&to=2026-11-30 -> 2 pertama; hanya from=2026-11-30 -> 2026-11-30 23:30 & 12-01; null tidak pernah ikut
  test_assignee_status_platform_and_flag_filters()
  test_except_skips_given_keys()
  test_options_only_come_from_visible_contents()
      // konten milik member lain berdivisi 'Rahasia' & PIC 'Orang Lain' -> tidak ada di opsi member A
  test_assignee_options_label_inactive_archived_and_unactivated()
  test_carry_contains_only_filled_view_independent_keys()
      // month/from/to/sort tidak pernah ikut
  ```

  Untuk membuat request di tes: `Request::create('/timeline/list', 'GET', [...])`, lalu `TimelineFilters::fromRequest($request, true)`.

- [ ] **Step 2: Jalankan tes, pastikan gagal**

  Run: `php -d extension=pdo_sqlite vendor/bin/phpunit --filter TimelineFiltersTest`
  Expected: FAIL.

- [ ] **Step 3: Implementasi**

  - **Aturan validasi:**
    - `status`: `Rule::in(array_keys(Content::STATUSES))`
    - `platform`: `Rule::in(Content::PLATFORMS)`
    - `division`: `nullable|string|max:120`
    - `assignee`: `nullable|integer|exists:users,id` (tanpa syarat `deleted_at`)
    - `flag`: `Rule::in(self::FLAGS)`
    - bila `$withRange`: `from` `nullable|date_format:Y-m-d`, `to` `nullable|date_format:Y-m-d|after_or_equal:from`
  - **Rentang:** `from` di-parse dengan `CarbonImmutable::createFromFormat('!Y-m-d', ..., config('app.timezone'))->startOfDay()`, `to` dengan `->endOfDay()`.
  - **Filter divisi:** `whereRaw('LOWER(TRIM(division)) = ?', [User::divisionKey($value)])`.

- [ ] **Step 4: Jalankan tes, pastikan lulus.** Jalankan juga seluruh suite.

- [ ] **Step 5: Commit**

  `feat(timeline): filter timeline bersama`

### Task 5: Tab, form filter bersama, dan integrasi kalender

**Files:**
- Create: `resources/views/components/timeline-tabs.blade.php`, `resources/views/components/timeline-filter-form.blade.php`
- Modify: `app/Http/Controllers/TimelineController.php`, `resources/views/timeline/index.blade.php`, `resources/css/app.css`
- Test: `tests/Feature/TimelineCalendarFiltersTest.php`; `tests/Feature/TimelinePageTest.php` dan `TimelineIntegrationTest.php` harus tetap lulus **tanpa diubah**.

**Interfaces:**
- Consumes: `TimelineFilters` (Task 4), `<x-content-flags>` (Task 3).
- Produces:
  - `<x-timeline-tabs current="calendar|list|board" :carry="array" />`. Isinya tiga tautan ke `timeline.index`, `timeline.list`, dan `timeline.board`, masing-masing dengan query `carry`. Tautan aktif diberi `aria-current="page"`.
  - `<x-timeline-filter-form :action="string" :filters="TimelineFilters" :divisions="Collection" :assignees="Collection" :show-status="bool">`. Slot default berisi field khusus tampilan (`month` atau `from`/`to`). Field bersama: Status (bila `show-status`), Platform, Divisi, PIC, Penanda. Setiap field punya `<label for>` yang benar.
  - View variable kalender tambahan: `filters`, `divisions`, `assignees`.

- [ ] **Step 1: Tulis tes yang gagal**

  ```php
  test_calendar_filters_by_division_assignee_and_flag()
  test_calendar_month_navigation_keeps_new_filters()
      // previousUrl/nextUrl memuat division & assignee & flag
  test_calendar_shows_view_tabs_carrying_filters()
      // assertSee href timeline.list?division=...&platform=... ; aria-current pada Kalender
  test_calendar_cards_show_flags()
  ```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

  Run: `php -d extension=pdo_sqlite vendor/bin/phpunit --filter TimelineCalendarFiltersTest`
  Expected: FAIL.

- [ ] **Step 3: Implementasi**

  - **`TimelineController::index`:**
    - Validasi `month` seperti sekarang, lalu `$filters = TimelineFilters::fromRequest($request, false)`.
    - Query: `Content::visibleTo($user)->forTimelineRange($start, $end)`, lalu `$filters->apply($query)`.
    - Closure `$url` menggabungkan `['month' => ...] + $filters->carry()`.
    - Kirim `filters`, `divisions`, dan `assignees` ke view.
  - **View kalender:**
    - Sisipkan `<x-timeline-tabs current="calendar">` di bawah `page-heading`.
    - Ganti `<form class="timeline-filters">` dengan `<x-timeline-filter-form>` yang berisi field `month`. Pertahankan `id="timeline-month"`, `id="timeline-status"`, dan `id="timeline-platform"` beserta label lamanya, karena tes lama mungkin mencarinya. Cek dengan `grep -n "timeline-month\|timeline-status\|timeline-platform\|Tampilkan" tests/Feature`.
    - Tambahkan `<x-content-flags :content="$content" compact/>` di `timeline-event-heading`.
  - **CSS:**
    - Grid `.timeline-filters` sekarang berisi 6–7 field. Ubah menjadi `repeat(auto-fit, minmax(160px, 1fr)) auto` atau yang setara, supaya tetap satu kolom di ≤767px.
    - `.timeline-tabs` memakai gaya seperti `.status-tabs`.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

  Run: seluruh suite.
  Expected: semua lulus, termasuk `TimelinePageTest` dan `TimelineIntegrationTest` tanpa perubahan.

- [ ] **Step 5: Commit**

  `feat(timeline): tab tampilan dan filter bersama di kalender`

### Task 6: Tampilan Daftar `/timeline/list`

**Files:**
- Create: `app/Http/Controllers/TimelineListController.php` (invokable), `resources/views/timeline/list.blade.php`
- Modify: `routes/web.php` (di grup `auth` + `EnsureActiveMember`, di sebelah route `timeline.index`), `resources/css/app.css` bila perlu
- Test: `tests/Feature/TimelineListTest.php`

**Interfaces:**
- Consumes: Task 3–5.
- Produces: route `timeline.list`. View menerima `contents` (paginator), `filters`, `divisions`, `assignees`, `sort`, dan `summary` (`['total' => int, 'overdue' => int, 'needs_assignee' => int]`).

- [ ] **Step 1: Tulis tes yang gagal**

  ```php
  test_list_shows_only_visible_contents()                 // member A tidak melihat konten member B
  test_default_sort_is_schedule_ascending_with_unscheduled_last()
  test_sort_desc_and_sort_by_status_follow_workflow_order()
      // status: draft, in_review, revision, ready, published (urutan Content::STATUSES), tie-break scheduled_at
  test_every_filter_applies()                              // status, platform, division, assignee, from/to, flag
  test_invalid_parameters_return_validation_errors_not_500()
      // from>to, flag=xyz, assignee=abc, sort=foo -> assertSessionHasErrors([...]) dan status 302
  test_pagination_is_25_and_keeps_query_string()           // 26 konten -> halaman 2 berisi 1, link membawa division
  test_summary_counts_ignore_flag_filter_and_link_to_flags()
      // ?flag=overdue tetap menampilkan summary total/overdue/needs_assignee dari filter lain
  test_row_shows_status_flags_pic_division_and_wib_schedule()
      // 'Belum dijadwalkan' untuk null; '09:00 WIB'
  test_empty_states_distinguish_no_content_and_no_match()
  test_response_is_private_no_store_and_tab_list_is_current()
  ```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

  Run: `php -d extension=pdo_sqlite vendor/bin/phpunit --filter TimelineListTest`
  Expected: FAIL.

- [ ] **Step 3: Implementasi**

  - **Validasi `sort`:** `nullable|in:scheduled,-scheduled,status`, default `scheduled`.
  - **Urutan:**
    - `scheduled`: `orderByRaw('scheduled_at IS NULL')->orderBy('scheduled_at')->orderBy('id')`
    - `-scheduled`: `orderByRaw('scheduled_at IS NULL')->orderByDesc('scheduled_at')->orderBy('id')`
    - `status`: `orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 … END', array_keys(Content::STATUSES))`, lalu urutan `scheduled`
  - **Eager load:** `with('assignee')->withCount('references')`. Paginasi 25 dengan `withQueryString()`.
  - **Ringkasan:** dari `$filters->apply(Content::visibleTo($user), ['flag'])`, hitung `count()`, `->overdue()->count()`, dan `->needsAssignee()->count()` (clone query).
  - **Markup:** pola `content-list`/`content-row`/`list-header`. Kolom tambahan Divisi dan Penanda boleh digabung ke baris meta di bawah judul, supaya grid kolom lama tidak perlu diubah.
  - **Kontrol urutan:** `<select name="sort">` di dalam slot form filter.

- [ ] **Step 4: Jalankan tes, pastikan lulus.** Jalankan juga seluruh suite.

- [ ] **Step 5: Commit**

  `feat(timeline): tampilan daftar`

### Task 7: Tampilan Papan `/timeline/board`

**Files:**
- Create: `app/Http/Controllers/TimelineBoardController.php` (invokable), `resources/views/timeline/board.blade.php`
- Modify: `routes/web.php`, `resources/css/app.css`
- Test: `tests/Feature/TimelineBoardTest.php`

**Interfaces:**
- Consumes: Task 3–5.
- Produces:
  - Route `timeline.board`.
  - View menerima `columns`: array berurutan menurut `Content::STATUSES`, masing-masing `['status' => string, 'label' => string, 'total' => int, 'items' => Collection (maks 50), 'more' => bool, 'limitedToRecent' => bool]`.
  - Variabel lain: `filters`, `divisions`, `assignees`.

- [ ] **Step 1: Tulis tes yang gagal**

  ```php
  test_board_groups_visible_contents_into_five_ordered_columns()
  test_status_filter_is_ignored_on_board()                 // ?status=draft tetap menampilkan kolom lain berisi
  test_published_column_limited_to_last_30_days_with_note()
      // travelTo 2026-11-24 12:00 WIB; published_at 2026-10-26 (ikut) & 2026-10-24 (tidak); catatan tampil
  test_any_date_bound_lifts_30_day_limit_and_filters_by_schedule()
      // ?from=2026-10-01 -> published lama ikut bila scheduled_at >= from; catatan tidak tampil; konten tanpa jadwal tidak ikut
  test_column_shows_50_cards_and_link_to_list_when_more()
      // 51 draft -> 50 kartu, total 51, link timeline.list?status=draft (+carry)
  test_cards_sorted_by_schedule_unscheduled_last_and_show_flags_pic_platform()
  test_board_filters_apply_and_privacy_holds()
  test_board_tab_is_current_and_response_private()
  ```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

  Run: `php -d extension=pdo_sqlite vendor/bin/phpunit --filter TimelineBoardTest`
  Expected: FAIL.

- [ ] **Step 3: Implementasi**

  - **Basis query:** `$filters->apply(Content::visibleTo($user), ['status'])`.
  - **Per status:** clone basis, `where('status', $key)`.
  - **Untuk `published` tanpa `hasRange()`:** tambahkan `where('published_at', '>=', now()->subDays(30))` dan set `limitedToRecent = true`.
  - **Pengambilan data:** `total = count()`, items diambil dengan `take(50)` memakai urutan jadwal yang sama dengan daftar (null di akhir), lalu `with('assignee')->withCount('references')`.
  - **Tata letak:**
    - Desktop ≥1280px: `.timeline-board` berupa grid 5 kolom dengan lebar minimum 0, dan isi kolom boleh `overflow-y: auto` dengan `max-height`.
    - ≤1279px: satu kolom, setiap status menjadi `<section>` berjudul.
    - Halaman tidak boleh menggulir menyamping.
  - **Markup kolom:** setiap kolom berupa `<section aria-labelledby>` dengan `<h2>` berisi label + jumlah, dan kartu `<article>` dengan judul `<h3><a>`.

- [ ] **Step 4: Jalankan tes, pastikan lulus.** Jalankan juga seluruh suite.

- [ ] **Step 5: Commit**

  `feat(timeline): tampilan papan status`

### Task 8: Verifikasi akhir

**Files:** tidak ada file baru. Perbaiki temuan di file milik task terkait.

- [ ] **Step 1:** `php -d extension=pdo_sqlite vendor/bin/phpunit`. Expected: semua lulus, kira-kira 79 + 45 tes, tanpa error atau deprecation baru dari kode kita.
- [ ] **Step 2:** `vendor/bin/pint --test` pada semua file baru. Expected: tanpa perubahan.
- [ ] **Step 3:** `npm ci` (bila `node_modules` belum ada), lalu `npm run build`. Expected: build Vite sukses.
- [ ] **Step 4: Cek manual di browser.**
  1. Jalankan `php -d extension=pdo_sqlite artisan migrate:fresh` pada database SQLite lokal terpisah (atur `.env` lokal ke `DB_CONNECTION=sqlite`, **jangan** pakai MySQL `internal-pm` milik tim). Lalu `php artisan db:seed --class=WorkspaceDemoSeeder` dan `php -d extension=pdo_sqlite artisan serve`.
  2. Buka `/timeline`, `/timeline/list`, `/timeline/board`, dan detail konten di lebar 1440, 1024, 768, dan 320px.
  3. Cek: tidak ada gulir menyamping, tab dan fokus keyboard terlihat, penanda terbaca, dan filter terbawa saat pindah tab.
- [ ] **Step 5:** Perbarui `docs/CONTENT-TIMELINE-INTEGRATION.md` dengan bagian singkat "Daftar, papan, penanda, dan riwayat (F1)": route, parameter filter, dan catatan agar status/jadwal/PIC diubah lewat model. Commit `docs(timeline): catat integrasi daftar, papan, dan riwayat`.
