<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00:00', 'Asia/Jakarta'));
    }

    private function actor(string $role = 'admin', array $attributes = []): User
    {
        return User::factory()->create(['role' => $role, 'division' => 'Media', 'is_active' => true, ...$attributes]);
    }

    private function content(User $owner, string $title, array $attributes = []): Content
    {
        $forced = ['created_by' => $owner->id, 'status' => $attributes['status'] ?? 'draft'];
        if (isset($attributes['published_at'])) {
            $forced['published_at'] = $attributes['published_at'];
        }
        unset($attributes['status'], $attributes['published_at']);
        $content = new Content([...['title' => $title, 'platform' => 'Instagram', 'assigned_to' => $owner->id,
            'division' => 'Media', 'scheduled_at' => '2026-11-26 09:00:00'], ...$attributes]);
        $content->forceFill($forced)->save();

        return $content;
    }

    private function columns(array $query = []): array
    {
        $columns = null;
        $this->get(route('timeline.board', $query))->assertOk()->assertViewHas('columns', function ($value) use (&$columns) {
            $columns = collect($value)->keyBy('status')->all();

            return true;
        });

        return $columns;
    }

    public function test_board_groups_visible_contents_into_five_ordered_columns(): void
    {
        $admin = $this->actor();
        $draft = $this->content($admin, 'Draf');
        $review = $this->content($admin, 'Review', ['status' => 'in_review']);
        $ready = $this->content($admin, 'Siap', ['status' => 'ready']);

        $this->actingAs($admin);
        $columns = $this->columns();
        $this->assertSame(['draft', 'in_review', 'revision', 'ready', 'published'], array_keys($columns));
        $this->assertSame([$draft->id], $columns['draft']['items']->modelKeys());
        $this->assertSame([$review->id], $columns['in_review']['items']->modelKeys());
        $this->assertSame([$ready->id], $columns['ready']['items']->modelKeys());
        $this->assertSame(0, $columns['revision']['total']);
        $this->get(route('timeline.board'))->assertSeeInOrder(['Draf', 'Dalam review', 'Perlu revisi', 'Siap upload', 'Terunggah']);
    }

    public function test_status_filter_is_ignored_on_board(): void
    {
        $admin = $this->actor();
        $this->content($admin, 'Draf');
        $review = $this->content($admin, 'Review', ['status' => 'in_review']);

        $this->actingAs($admin);
        $this->assertSame([$review->id], $this->columns(['status' => 'draft'])['in_review']['items']->modelKeys());
    }

    public function test_published_column_limited_to_last_30_days_with_note(): void
    {
        $admin = $this->actor();
        $recent = $this->content($admin, 'Baru terbit', ['status' => 'published', 'published_at' => '2026-10-26 09:00:00']);
        $this->content($admin, 'Lama terbit', ['status' => 'published', 'published_at' => '2026-10-24 09:00:00']);

        $this->actingAs($admin);
        $published = $this->columns()['published'];
        $this->assertSame([$recent->id], $published['items']->modelKeys());
        $this->assertTrue($published['limitedToRecent']);
        $this->get(route('timeline.board'))->assertSee('Menampilkan yang terunggah 30 hari terakhir')->assertDontSee('Lama terbit');
    }

    public function test_any_date_bound_lifts_30_day_limit_and_filters_by_schedule(): void
    {
        $admin = $this->actor();
        $old = $this->content($admin, 'Lama terbit', ['status' => 'published', 'published_at' => '2026-10-10 09:00:00', 'scheduled_at' => '2026-10-10 08:00:00']);
        $this->content($admin, 'Terbit sebelum rentang', ['status' => 'published', 'published_at' => '2026-09-10 09:00:00', 'scheduled_at' => '2026-09-10 08:00:00']);
        $this->content($admin, 'Tanpa jadwal', ['scheduled_at' => null]);

        $this->actingAs($admin);
        $columns = $this->columns(['from' => '2026-10-01']);
        $this->assertSame([$old->id], $columns['published']['items']->modelKeys());
        $this->assertFalse($columns['published']['limitedToRecent']);
        $this->assertSame(0, $columns['draft']['total']);
        $this->get(route('timeline.board', ['from' => '2026-10-01']))->assertDontSee('Menampilkan yang terunggah 30 hari terakhir');
    }

    public function test_column_shows_50_cards_and_link_to_list_when_more(): void
    {
        $admin = $this->actor();
        foreach (range(1, 51) as $i) {
            $this->content($admin, 'Konten '.$i, ['scheduled_at' => CarbonImmutable::parse('2026-11-25 00:00:00')->addMinutes($i)]);
        }

        $this->actingAs($admin);
        $draft = $this->columns(['division' => 'Media'])['draft'];
        $this->assertCount(50, $draft['items']);
        $this->assertSame(51, $draft['total']);
        $this->assertTrue($draft['more']);
        $this->get(route('timeline.board', ['division' => 'Media']))
            ->assertSee('href="'.e(route('timeline.list', ['division' => 'Media', 'status' => 'draft'])).'"', false)
            ->assertSee('Lihat semua di Daftar');
    }

    public function test_see_all_link_keeps_date_range_of_the_column(): void
    {
        $admin = $this->actor();
        foreach (range(1, 51) as $i) {
            $this->content($admin, 'Konten '.$i, ['scheduled_at' => CarbonImmutable::parse('2026-12-02 00:00:00')->addMinutes($i)]);
        }

        $this->actingAs($admin)->get(route('timeline.board', ['from' => '2026-12-01', 'to' => '2026-12-31']))->assertOk()
            ->assertSee('href="'.e(route('timeline.list', ['from' => '2026-12-01', 'to' => '2026-12-31', 'status' => 'draft'])).'"', false);
    }

    public function test_recent_published_column_link_does_not_promise_the_same_set(): void
    {
        $admin = $this->actor();
        foreach (range(1, 51) as $i) {
            $this->content($admin, 'Terbit '.$i, ['status' => 'published', 'published_at' => '2026-11-20 09:00:00']);
        }

        $this->actingAs($admin)->get(route('timeline.board'))->assertOk()
            ->assertSee('Lihat semua konten terunggah di Daftar')->assertDontSee('Lihat semua di Daftar');
    }

    public function test_cards_sorted_by_schedule_unscheduled_last_and_show_flags_pic_platform(): void
    {
        $admin = $this->actor('admin', ['name' => 'Rani']);
        $unscheduled = $this->content($admin, 'Tanpa jadwal', ['scheduled_at' => null]);
        $late = $this->content($admin, 'Telat', ['scheduled_at' => '2026-11-20 09:00:00', 'platform' => 'TikTok']);
        $soon = $this->content($admin, 'Segera', ['scheduled_at' => '2026-11-25 09:00:00']);

        $this->actingAs($admin);
        $this->assertSame([$late->id, $soon->id, $unscheduled->id], $this->columns()['draft']['items']->modelKeys());
        $this->get(route('timeline.board'))->assertSee('Lewat tenggat')->assertSee('TikTok')->assertSee('Rani')
            ->assertSee('Belum dijadwalkan')->assertSee('09:00 WIB');
    }

    public function test_board_filters_apply_and_privacy_holds(): void
    {
        $member = $this->actor('member');
        $other = $this->actor('member');
        $own = $this->content($member, 'Punyaku', ['platform' => 'TikTok']);
        $this->content($member, 'Punyaku IG');
        $this->content($other, 'Punya Orang Lain', ['platform' => 'TikTok']);

        $this->actingAs($member);
        $this->assertSame([$own->id], $this->columns(['platform' => 'TikTok'])['draft']['items']->modelKeys());
        $this->get(route('timeline.board'))->assertDontSee('Punya Orang Lain');
    }

    public function test_board_tab_is_current_and_response_private(): void
    {
        $response = $this->actingAs($this->actor())->get(route('timeline.board'))->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Papan/', $response->getContent());
    }
}
