<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineCalendarFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00:00', 'Asia/Jakarta'));
    }

    private function actor(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'admin', 'division' => 'Media', 'is_active' => true, ...$attributes]);
    }

    private function content(User $owner, string $title, array $attributes = []): Content
    {
        $content = new Content([...['title' => $title, 'platform' => 'Instagram', 'assigned_to' => $owner->id,
            'division' => 'Media', 'scheduled_at' => '2026-11-26 09:00:00'], ...$attributes]);
        $content->forceFill(['created_by' => $owner->id, 'status' => 'draft'])->save();

        return $content;
    }

    public function test_calendar_filters_by_division_assignee_and_flag(): void
    {
        $admin = $this->actor();
        $pic = $this->actor(['role' => 'member', 'name' => 'Bima']);
        $kreatif = $this->content($admin, 'Kreatif', ['division' => 'Kreatif']);
        $assigned = $this->content($admin, 'Milik Bima', ['assigned_to' => $pic->id]);
        $late = $this->content($admin, 'Telat', ['scheduled_at' => '2026-11-20 09:00:00']);
        $keys = fn (array $query) => fn ($contents) => $contents->modelKeys() === $query;

        $this->actingAs($admin)->get(route('timeline.index', ['month' => '2026-11', 'division' => 'kreatif']))
            ->assertOk()->assertViewHas('contents', $keys([$kreatif->id]));
        $this->get(route('timeline.index', ['month' => '2026-11', 'assignee' => $pic->id]))
            ->assertViewHas('contents', $keys([$assigned->id]));
        $this->get(route('timeline.index', ['month' => '2026-11', 'flag' => 'overdue']))
            ->assertViewHas('contents', $keys([$late->id]));
    }

    public function test_calendar_month_navigation_keeps_new_filters(): void
    {
        $admin = $this->actor();
        $filters = ['division' => 'Media', 'assignee' => $admin->id, 'flag' => 'overdue'];

        $this->actingAs($admin)->get(route('timeline.index', ['month' => '2026-11', ...$filters]))->assertOk()
            ->assertViewHas('previousUrl', route('timeline.index', ['month' => '2026-10', ...$filters]))
            ->assertViewHas('nextUrl', route('timeline.index', ['month' => '2026-12', ...$filters]));
    }

    public function test_calendar_shows_view_tabs_carrying_filters(): void
    {
        $admin = $this->actor();
        $query = ['platform' => 'Instagram', 'division' => 'Media'];

        $response = $this->actingAs($admin)->get(route('timeline.index', ['month' => '2026-11', ...$query]))->assertOk()
            ->assertSee('href="'.e(route('timeline.list', $query)).'"', false)
            ->assertSee('href="'.e(route('timeline.board', $query)).'"', false)
            ->assertSee('Daftar')->assertSee('Papan');
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Kalender/', $response->getContent());
    }

    public function test_calendar_cards_show_flags(): void
    {
        $admin = $this->actor();
        $this->content($admin, 'Telat', ['scheduled_at' => '2026-11-20 09:00:00']);

        $this->actingAs($admin)->get(route('timeline.index', ['month' => '2026-11']))->assertOk()
            ->assertSee('aria-label="Lewat tenggat"', false)->assertSee('Telat');
    }
}
