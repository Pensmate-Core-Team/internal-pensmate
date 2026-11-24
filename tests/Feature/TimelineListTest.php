<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00:00', 'Asia/Jakarta'));
    }

    private function actor(string $role = 'member', array $attributes = []): User
    {
        return User::factory()->create(['role' => $role, 'division' => 'Media', 'is_active' => true, ...$attributes]);
    }

    private function content(User $owner, string $title, array $attributes = []): Content
    {
        $status = $attributes['status'] ?? 'draft';
        unset($attributes['status']);
        $content = new Content([...['title' => $title, 'platform' => 'Instagram', 'assigned_to' => $owner->id,
            'division' => 'Media', 'scheduled_at' => '2026-11-26 09:00:00'], ...$attributes]);
        $content->forceFill(['created_by' => $owner->id, 'status' => $status])->save();

        return $content;
    }

    private function listIds(array $query = []): array
    {
        $ids = null;
        $this->get(route('timeline.list', $query))->assertOk()
            ->assertViewHas('contents', function ($contents) use (&$ids) {
                $ids = $contents->getCollection()->modelKeys();

                return true;
            });

        return $ids;
    }

    public function test_list_shows_only_visible_contents(): void
    {
        $mine = $this->actor();
        $other = $this->actor();
        $own = $this->content($mine, 'Punyaku');
        $this->content($other, 'Punya Orang Lain');

        $this->actingAs($mine);
        $this->assertSame([$own->id], $this->listIds());
        $this->get(route('timeline.list'))->assertDontSee('Punya Orang Lain');
    }

    public function test_default_sort_is_schedule_ascending_with_unscheduled_last(): void
    {
        $admin = $this->actor('admin');
        $unscheduled = $this->content($admin, 'Tanpa jadwal', ['scheduled_at' => null]);
        $late = $this->content($admin, 'Akhir', ['scheduled_at' => '2026-11-28 09:00:00']);
        $early = $this->content($admin, 'Awal', ['scheduled_at' => '2026-11-25 09:00:00']);

        $this->actingAs($admin);
        $this->assertSame([$early->id, $late->id, $unscheduled->id], $this->listIds());
    }

    public function test_sort_desc_and_sort_by_status_follow_workflow_order(): void
    {
        $admin = $this->actor('admin');
        $published = $this->content($admin, 'Terbit', ['status' => 'published', 'scheduled_at' => '2026-11-20 09:00:00']);
        $revision = $this->content($admin, 'Revisi', ['status' => 'revision', 'scheduled_at' => '2026-11-21 09:00:00']);
        $draftLater = $this->content($admin, 'Draf B', ['scheduled_at' => '2026-11-27 09:00:00']);
        $review = $this->content($admin, 'Review', ['status' => 'in_review', 'scheduled_at' => '2026-11-22 09:00:00']);
        $draftEarly = $this->content($admin, 'Draf A', ['scheduled_at' => '2026-11-23 09:00:00']);
        $unscheduled = $this->content($admin, 'Tanpa jadwal', ['scheduled_at' => null, 'status' => 'ready']);

        $this->actingAs($admin);
        $this->assertSame([$draftEarly->id, $draftLater->id, $review->id, $revision->id, $unscheduled->id, $published->id],
            $this->listIds(['sort' => 'status']));
        $this->assertSame([$draftLater->id, $draftEarly->id, $review->id, $revision->id, $published->id, $unscheduled->id],
            $this->listIds(['sort' => '-scheduled']));
    }

    public function test_every_filter_applies(): void
    {
        $admin = $this->actor('admin');
        $pic = $this->actor('member', ['name' => 'Bima']);
        $kreatif = $this->content($admin, 'Kreatif', ['division' => 'Kreatif']);
        $tiktok = $this->content($admin, 'TikTok', ['platform' => 'TikTok']);
        $review = $this->content($admin, 'Review', ['status' => 'in_review']);
        $assigned = $this->content($admin, 'Bima', ['assigned_to' => $pic->id]);
        $late = $this->content($admin, 'Telat', ['scheduled_at' => '2026-11-20 09:00:00']);
        $december = $this->content($admin, 'Desember', ['scheduled_at' => '2026-12-05 09:00:00']);

        $this->actingAs($admin);
        $this->assertSame([$kreatif->id], $this->listIds(['division' => 'Kreatif']));
        $this->assertSame([$tiktok->id], $this->listIds(['platform' => 'TikTok']));
        $this->assertSame([$review->id], $this->listIds(['status' => 'in_review']));
        $this->assertSame([$assigned->id], $this->listIds(['assignee' => $pic->id]));
        $this->assertSame([$late->id], $this->listIds(['flag' => 'overdue']));
        $this->assertSame([$december->id], $this->listIds(['from' => '2026-12-01', 'to' => '2026-12-31']));
    }

    public function test_invalid_parameters_return_validation_errors_not_500(): void
    {
        $this->actingAs($this->actor());

        $this->get(route('timeline.list', ['from' => '2026-11-10', 'to' => '2026-11-01', 'flag' => 'xyz', 'assignee' => 'abc', 'sort' => 'foo']))
            ->assertStatus(302)->assertSessionHasErrors(['to', 'flag', 'assignee', 'sort']);
    }

    public function test_pagination_is_25_and_keeps_query_string(): void
    {
        $admin = $this->actor('admin');
        foreach (range(1, 26) as $i) {
            $this->content($admin, 'Konten '.$i, ['scheduled_at' => CarbonImmutable::parse('2026-11-01 09:00:00')->addHours($i)]);
        }

        $this->actingAs($admin);
        $this->assertCount(25, $this->listIds(['division' => 'Media']));
        $this->assertCount(1, $this->listIds(['division' => 'Media', 'page' => 2]));
        $this->get(route('timeline.list', ['division' => 'Media']))->assertSee('division=Media&amp;page=2', false);
    }

    public function test_summary_counts_ignore_flag_filter_and_link_to_flags(): void
    {
        $admin = $this->actor('admin');
        $inactive = $this->actor('member', ['is_active' => false]);
        $this->content($admin, 'Telat', ['scheduled_at' => '2026-11-20 09:00:00']);
        $this->content($admin, 'Tanpa PIC', ['assigned_to' => $inactive->id]);
        $this->content($admin, 'Normal');

        $this->actingAs($admin)->get(route('timeline.list', ['flag' => 'overdue']))->assertOk()
            ->assertViewHas('summary', ['total' => 3, 'overdue' => 1, 'needs_assignee' => 1])
            ->assertSee('href="'.e(route('timeline.list', ['flag' => 'needs_assignee'])).'"', false);
    }

    public function test_row_shows_status_flags_pic_division_and_wib_schedule(): void
    {
        $admin = $this->actor('admin', ['name' => 'Rani']);
        $this->content($admin, 'Terjadwal', ['scheduled_at' => '2026-11-20 09:00:00', 'division' => 'Kreatif']);
        $this->content($admin, 'Belum', ['scheduled_at' => null]);

        $this->actingAs($admin)->get(route('timeline.list'))->assertOk()
            ->assertSee('Terjadwal')->assertSee('Draf')->assertSee('Lewat tenggat')->assertSee('Rani')
            ->assertSee('Kreatif')->assertSee('09:00 WIB')->assertSee('Belum dijadwalkan');
    }

    public function test_empty_states_distinguish_no_content_and_no_match(): void
    {
        $admin = $this->actor('admin');
        $this->actingAs($admin)->get(route('timeline.list'))->assertOk()->assertSee('Belum ada konten');

        $this->content($admin, 'Ada');
        $this->get(route('timeline.list', ['platform' => 'TikTok']))->assertOk()->assertSee('Tidak ada konten yang cocok');
    }

    public function test_response_is_private_no_store_and_tab_list_is_current(): void
    {
        $response = $this->actingAs($this->actor())->get(route('timeline.list'))->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Daftar/', $response->getContent());
    }
}
