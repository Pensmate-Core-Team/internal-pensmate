<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use App\Support\TimelineFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TimelineFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00:00', 'Asia/Jakarta'));
    }

    private function actor(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'member', 'division' => 'Media', 'is_active' => true, ...$attributes]);
    }

    private function content(User $owner, array $attributes = []): Content
    {
        $status = $attributes['status'] ?? 'draft';
        unset($attributes['status']);
        $content = new Content([...['title' => 'Konten', 'platform' => 'Instagram', 'assigned_to' => $owner->id,
            'division' => 'Media', 'scheduled_at' => '2026-11-26 09:00:00'], ...$attributes]);
        $content->forceFill(['created_by' => $owner->id, 'status' => $status])->save();

        return $content;
    }

    private function filters(array $query, bool $withRange = true): TimelineFilters
    {
        return TimelineFilters::fromRequest(Request::create('/timeline/list', 'GET', $query), $withRange);
    }

    private function ids(TimelineFilters $filters, array $except = []): array
    {
        return $filters->apply(Content::query(), $except)->orderBy('id')->pluck('id')->all();
    }

    private function assertInvalid(array $query, string $key): void
    {
        try {
            $this->filters($query);
            $this->fail('Validasi seharusnya gagal untuk '.$key);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }

    public function test_validation_rules(): void
    {
        $this->assertInvalid(['from' => '2026-11-10', 'to' => '2026-11-01'], 'to');
        $this->assertInvalid(['flag' => 'xyz'], 'flag');
        $this->assertInvalid(['assignee' => 'abc'], 'assignee');
        $this->assertInvalid(['status' => 'unknown'], 'status');
        $this->assertInvalid(['division' => str_repeat('a', 121)], 'division');

        $calendar = $this->filters(['from' => 'bukan-tanggal', 'to' => '2026-01-01'], false);
        $this->assertFalse($calendar->hasRange());
    }

    public function test_division_filter_ignores_case_and_whitespace(): void
    {
        $owner = $this->actor();
        $a = $this->content($owner, ['division' => 'Kreatif']);
        $b = $this->content($owner, ['division' => 'kreatif']);
        $this->content($owner, ['division' => 'Produksi']);

        $this->assertSame([$a->id, $b->id], $this->ids($this->filters(['division' => ' KREATIF '])));
    }

    public function test_division_filter_matches_raw_values_with_inner_whitespace(): void
    {
        $owner = $this->actor();
        $squished = $this->content($owner, ['division' => 'Media Kreatif']);
        $raw = $this->content($owner);
        DB::table('contents')->where('id', $raw->id)->update(['division' => "Media  Kreatif\t"]);
        $this->content($owner, ['division' => 'Media']);

        $this->assertSame([$squished->id, $raw->id], $this->ids($this->filters(['division' => 'media kreatif'])));
    }

    public function test_division_options_sort_case_insensitively_and_prefer_common_then_title_spelling(): void
    {
        $owner = $this->actor();
        foreach (['Zeta', 'alpha', 'KREATIF', 'Kreatif', 'produksi', 'PRODUKSI', 'PRODUKSI'] as $division) {
            $this->content($owner, ['division' => $division]);
        }

        $this->assertSame(['alpha' => 'alpha', 'kreatif' => 'Kreatif', 'produksi' => 'PRODUKSI', 'zeta' => 'Zeta'],
            TimelineFilters::divisionOptions($owner)->all());
    }

    public function test_range_is_inclusive_in_wib_and_excludes_unscheduled(): void
    {
        $owner = $this->actor();
        $first = $this->content($owner, ['scheduled_at' => '2026-11-01 00:00:00']);
        $last = $this->content($owner, ['scheduled_at' => '2026-11-30 23:30:00']);
        $next = $this->content($owner, ['scheduled_at' => '2026-12-01 00:00:00']);
        $this->content($owner, ['scheduled_at' => null]);

        $this->assertSame([$first->id, $last->id], $this->ids($this->filters(['from' => '2026-11-01', 'to' => '2026-11-30'])));
        $this->assertSame([$last->id, $next->id], $this->ids($this->filters(['from' => '2026-11-30'])));
        $this->assertSame([$first->id], $this->ids($this->filters(['to' => '2026-11-01'])));
        $this->assertTrue($this->filters(['to' => '2026-11-01'])->hasRange());
    }

    public function test_assignee_status_platform_and_flag_filters(): void
    {
        $owner = $this->actor();
        $other = $this->actor();
        $overdue = $this->content($owner, ['scheduled_at' => '2026-11-20 09:00:00', 'status' => 'in_review']);
        $tiktok = $this->content($owner, ['platform' => 'TikTok']);
        $assigned = $this->content($owner, ['assigned_to' => $other->id]);
        $inactive = $this->actor(['is_active' => false]);
        $orphan = $this->content($owner, ['assigned_to' => $inactive->id]);

        $this->assertSame([$assigned->id], $this->ids($this->filters(['assignee' => $other->id])));
        $this->assertSame([$overdue->id], $this->ids($this->filters(['status' => 'in_review'])));
        $this->assertSame([$tiktok->id], $this->ids($this->filters(['platform' => 'TikTok'])));
        $this->assertSame([$overdue->id], $this->ids($this->filters(['flag' => 'overdue'])));
        $this->assertSame([$orphan->id], $this->ids($this->filters(['flag' => 'needs_assignee'])));
    }

    public function test_except_skips_given_keys(): void
    {
        $owner = $this->actor();
        $draft = $this->content($owner);
        $review = $this->content($owner, ['status' => 'in_review']);

        $this->assertSame([$draft->id, $review->id], $this->ids($this->filters(['status' => 'draft']), ['status']));
    }

    public function test_options_only_come_from_visible_contents(): void
    {
        $viewer = $this->actor(['name' => 'Ayu']);
        $stranger = $this->actor(['name' => 'Orang Lain', 'division' => 'Rahasia']);
        $this->content($viewer, ['division' => 'Kreatif']);
        $this->content($viewer, ['division' => 'kreatif ']);
        $this->content($stranger, ['division' => 'Rahasia']);

        $this->assertSame(['kreatif' => 'Kreatif'], TimelineFilters::divisionOptions($viewer)->all());
        $this->assertSame(['Ayu'], TimelineFilters::assigneeOptions($viewer)->pluck('label')->all());
    }

    public function test_assignee_options_label_inactive_archived_and_unactivated(): void
    {
        $admin = $this->actor(['role' => 'admin', 'name' => 'Admin']);
        $inactive = $this->actor(['name' => 'Bima', 'is_active' => false]);
        $archived = $this->actor(['name' => 'Citra']);
        $unactivated = $this->actor(['name' => 'Dodi', 'email' => null]);
        foreach ([$inactive, $archived, $unactivated] as $pic) {
            $this->content($admin, ['assigned_to' => $pic->id]);
        }
        $archived->delete();

        $this->assertSame(['Bima (nonaktif)', 'Citra (nonaktif)', 'Dodi (belum aktivasi)'],
            TimelineFilters::assigneeOptions($admin)->pluck('label')->all());
    }

    public function test_carry_contains_only_filled_view_independent_keys(): void
    {
        $filters = $this->filters(['status' => 'draft', 'platform' => '', 'division' => 'Media', 'from' => '2026-11-01',
            'to' => '2026-11-30', 'month' => '2026-11', 'sort' => 'status', 'flag' => 'overdue']);

        $this->assertSame(['status' => 'draft', 'division' => 'Media', 'flag' => 'overdue'], $filters->carry());
    }
}
