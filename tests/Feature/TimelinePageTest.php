<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelinePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function actor(string $role = 'member', string $division = 'Media'): User
    {
        return User::factory()->create(['role' => $role, 'division' => $division, 'is_active' => true]);
    }

    private function content(User $owner, string $title = 'Jadwal Pengujian', ?string $date = '2026-11-20 09:00:00', string $status = 'draft', string $platform = 'Instagram'): Content
    {
        $content = new Content(['title' => $title, 'platform' => $platform, 'assigned_to' => $owner->id,
            'division' => $owner->division, 'scheduled_at' => $date]);
        $content->forceFill(['created_by' => $owner->id, 'status' => $status])->save();

        return $content;
    }

    private function timeline(array $filters = []): string
    {
        return route('timeline.index', [...['month' => '2026-11'], ...$filters]);
    }

    public function test_timeline_is_reachable_from_sidebar_and_has_an_honest_empty_state(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 12:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->actor())->get('/contents')->assertOk()->assertSee('href="'.route('timeline.index').'"', false);
        $this->get(route('timeline.index'))->assertOk()->assertViewHas('month', '2026-11')
            ->assertSee('November 2026')->assertSee('Belum ada konten terjadwal')->assertSee('Buka draf konten');
    }

    public function test_events_show_clickable_reference_links_and_a_path_to_the_same_content_detail(): void
    {
        $owner = $this->actor();
        $content = $this->content($owner);
        $content->references()->create(['title' => 'Referensi desain', 'url' => 'https://example.org/design',
            'notes' => 'Perhatikan warna dan komposisi.', 'created_by' => $owner->id]);
        $content->references()->create(['title' => 'Referensi naskah', 'url' => 'https://example.org/script',
            'created_by' => $owner->id]);
        $this->actingAs($owner)->get($this->timeline())->assertOk()->assertSee('Jadwal Pengujian')
            ->assertSee('09:00 WIB')->assertSee('2 referensi')->assertSee('Perhatikan warna dan komposisi.')
            ->assertSee('href="https://example.org/design"', false)
            ->assertSee('href="https://example.org/script"', false)
            ->assertSee('href="'.route('contents.show', $content).'#references"', false);
        $this->get(route('contents.show', $content))->assertOk()->assertSee('id="references"', false)
            ->assertSee('href="'.$this->timeline().'#timeline-content-'.$content->id.'"', false);
    }

    public function test_reference_crud_from_detail_updates_timeline_without_duplicate_storage(): void
    {
        $owner = $this->actor();
        $content = $this->content($owner);
        $detail = route('contents.show', $content);
        $this->actingAs($owner)->get($this->timeline())->assertSee('0 referensi')->assertSee('Tambah referensi');
        $this->from($detail)->post(route('references.store', $content), [
            'title' => 'Rujukan awal', 'url' => 'https://example.org/first', 'notes' => 'Catatan awal',
        ])->assertRedirect($detail);
        $reference = $content->references()->firstOrFail();
        $this->get($this->timeline())->assertSee('Rujukan awal')->assertSee('1 referensi');
        $this->from($detail)->put(route('references.update', [$content, $reference]), [
            'title' => 'Rujukan diperbarui', 'url' => 'https://example.org/updated', 'notes' => 'Catatan baru',
        ])->assertRedirect($detail);
        $this->get($this->timeline())->assertSee('Rujukan diperbarui')->assertSee('Catatan baru')->assertDontSee('Rujukan awal');
        $this->assertDatabaseCount('content_references', 1);
        $this->from($detail)->delete(route('references.destroy', [$content, $reference]))->assertRedirect($detail);
        $this->get($this->timeline())->assertSee('0 referensi')->assertDontSee('Rujukan diperbarui');
        $this->assertDatabaseCount('content_references', 0);
    }

    public function test_month_boundaries_and_feed_show_the_same_ordered_content(): void
    {
        $owner = $this->actor();
        $this->content($owner, 'Di luar Oktober', '2026-10-31 23:59:59');
        $last = $this->content($owner, 'Akhir November', '2026-11-30 23:59:59');
        $first = $this->content($owner, 'Awal November', '2026-11-01 00:00:00');
        $this->content($owner, 'Di luar Desember', '2026-12-01 00:00:00');
        $this->content($owner, 'Tanpa jadwal', null);
        $this->actingAs($owner)->get($this->timeline())->assertOk()->assertViewHas('contents',
            fn ($contents) => $contents->modelKeys() === [$first->id, $last->id])
            ->assertDontSee('Di luar Oktober')->assertDontSee('Di luar Desember')->assertDontSee('Tanpa jadwal');
        $this->getJson(route('timeline.contents', ['start' => '2026-11-01', 'end' => '2026-11-30']))
            ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $last->id);
    }

    public function test_filters_persist_when_navigating_between_months(): void
    {
        $owner = $this->actor();
        $wanted = $this->content($owner, 'Instagram disetujui', status: 'ready');
        $this->content($owner, 'Instagram draf');
        $this->content($owner, 'YouTube disetujui', status: 'ready', platform: 'YouTube');
        $filters = ['status' => 'ready', 'platform' => 'Instagram'];
        $this->actingAs($owner)->get($this->timeline($filters))->assertOk()
            ->assertViewHas('contents', fn ($contents) => $contents->modelKeys() === [$wanted->id])
            ->assertViewHas('previousUrl', route('timeline.index', ['month' => '2026-10', ...$filters]))
            ->assertViewHas('nextUrl', route('timeline.index', ['month' => '2026-12', ...$filters]));
    }

    public function test_hidden_content_and_reference_notes_never_leak_to_other_members(): void
    {
        $owner = $this->actor();
        $visible = $this->content($owner, 'Konten milik sendiri');
        $private = $this->content($this->actor(), 'Judul rahasia anggota lain');
        $private->references()->create(['title' => 'Referensi rahasia', 'url' => 'https://example.org/private',
            'notes' => 'Catatan rahasia', 'created_by' => $private->created_by]);
        $this->actingAs($owner)->get($this->timeline())->assertOk()->assertSee($visible->title)
            ->assertDontSee($private->title)->assertDontSee('Referensi rahasia')->assertDontSee('Catatan rahasia');
        $this->get(route('contents.show', $private))->assertForbidden();
    }

    public function test_coordinator_sees_only_reviewable_other_division_events(): void
    {
        $coordinator = $this->actor('coordinator', 'Operasional');
        $allowed = $this->content($this->actor(), 'Perlu review lintas divisi', status: 'in_review');
        $this->content($this->actor('member', 'Operasional'), 'Divisi sendiri', status: 'in_review');
        $this->content($this->actor(), 'Draf privat');
        $this->actingAs($coordinator)->get($this->timeline())->assertOk()
            ->assertViewHas('contents', fn ($contents) => $contents->modelKeys() === [$allowed->id])
            ->assertDontSee('Divisi sendiri')->assertDontSee('Draf privat');
    }

    public function test_locked_references_remain_readable_but_cannot_be_modified_from_timeline(): void
    {
        $owner = $this->actor();
        $content = $this->content($owner, status: 'in_review');
        $content->references()->create(['title' => 'Rujukan terkunci', 'url' => 'https://example.org/locked', 'created_by' => $owner->id]);
        $this->actingAs($owner)->get($this->timeline())->assertOk()->assertSee('Rujukan terkunci')
            ->assertSee('Buka detail referensi')->assertDontSee('Kelola referensi');
        $this->post(route('references.store', $content), ['title' => 'Tidak boleh', 'url' => 'https://example.org'])
            ->assertForbidden();
    }

    public function test_reference_titles_and_notes_are_escaped_in_calendar_markup(): void
    {
        $owner = $this->actor();
        $content = $this->content($owner);
        $content->references()->create(['title' => '<script>unsafe()</script>', 'url' => 'https://example.org/safe',
            'notes' => '<img src=x onerror=unsafe()>', 'created_by' => $owner->id]);
        $this->actingAs($owner)->get($this->timeline())->assertOk()
            ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false)
            ->assertDontSee('<img src=x onerror=unsafe()>', false);
    }

    public function test_calendar_renders_all_month_events_without_the_api_page_limit(): void
    {
        $owner = $this->actor();
        $rows = [];
        for ($i = 0; $i < 101; $i++) {
            $rows[] = ['title' => 'Jadwal '.$i, 'platform' => 'Instagram', 'created_by' => $owner->id,
                'assigned_to' => $owner->id, 'status' => 'draft', 'scheduled_at' => '2026-11-20 10:00:00',
                'created_at' => now(), 'updated_at' => now()];
        }
        Content::insert($rows);
        $response = $this->actingAs($owner)->get($this->timeline())->assertOk();
        $this->assertSame(101, substr_count($response->getContent(), 'data-timeline-content="'));
    }

    public function test_calendar_validates_month_filters_and_has_monday_first_weeks(): void
    {
        $owner = $this->actor();
        $this->content($owner);
        $this->actingAs($owner)->get($this->timeline())->assertViewHas('days',
            fn ($days) => count($days) === 42 && $days[0]['date']->dayOfWeekIso === 1
                && $days[41]['date']->dayOfWeekIso === 7);
        $this->getJson($this->timeline(['month' => '2026-13']))->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->getJson($this->timeline(['month' => 'not-a-month']))->assertUnprocessable();
        $this->getJson($this->timeline(['status' => 'invalid']))->assertUnprocessable();
        $this->getJson($this->timeline(['platform' => 'invalid']))->assertUnprocessable();
    }

    public function test_guests_and_inactive_accounts_cannot_open_timeline(): void
    {
        $this->get($this->timeline())->assertRedirect(route('login'));
        $owner = $this->actor();
        $owner->update(['is_active' => false]);
        $this->actingAs($owner)->get($this->timeline())->assertRedirect(route('login'));
    }
}
