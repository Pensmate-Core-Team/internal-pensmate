<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ContentFlagsTest extends TestCase
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
        return User::factory()->create(['role' => 'member', 'division' => 'Media', 'is_active' => true, ...$attributes]);
    }

    private function content(User $owner, ?string $date, string $status = 'draft', ?User $assignee = null): Content
    {
        $content = new Content(['title' => 'Konten '.$status.' '.$date, 'platform' => 'Instagram',
            'assigned_to' => ($assignee ?? $owner)->id, 'division' => 'Media', 'scheduled_at' => $date]);
        $content->forceFill(['created_by' => $owner->id, 'status' => $status])->save();

        return $content->fresh();
    }

    public function test_overdue_only_for_past_schedule_and_not_published(): void
    {
        $owner = $this->actor();
        $justPast = $this->content($owner, '2026-11-24 11:59:00');
        $future = $this->content($owner, '2026-11-24 12:01:00');
        $unscheduled = $this->content($owner, null);
        $published = $this->content($owner, '2026-11-23 10:00:00', 'published');
        $ready = $this->content($owner, '2026-11-23 10:00:00', 'ready');

        $this->assertTrue($justPast->isOverdue());
        $this->assertFalse($future->isOverdue());
        $this->assertFalse($unscheduled->isOverdue());
        $this->assertFalse($published->isOverdue());
        $this->assertTrue($ready->isOverdue());
        $this->assertEqualsCanonicalizing([$justPast->id, $ready->id], Content::overdue()->pluck('id')->all());
    }

    public function test_needs_assignee_for_inactive_archived_or_unactivated_pic(): void
    {
        $owner = $this->actor();
        $healthy = $this->content($owner, null);
        $inactive = $this->content($owner, null, 'draft', $this->actor(['is_active' => false]));
        $archivedPic = $this->actor();
        $archived = $this->content($owner, null, 'in_review', $archivedPic);
        $archivedPic->delete();
        $unactivatedPic = $this->actor(['email' => null]);
        $unactivated = $this->content($owner, null, 'ready', $unactivatedPic);
        $publishedUnactivated = $this->content($owner, null, 'published', $unactivatedPic);

        $this->assertFalse($healthy->needsAssignee());
        $this->assertTrue($inactive->needsAssignee());
        $this->assertTrue($archived->fresh()->needsAssignee());
        $this->assertTrue($unactivated->needsAssignee());
        $this->assertFalse($publishedUnactivated->needsAssignee());
        $this->assertEqualsCanonicalizing([$inactive->id, $archived->id, $unactivated->id], Content::needingAssignee()->pluck('id')->all());
    }

    public function test_flags_render_as_text_not_only_color(): void
    {
        $owner = $this->actor();
        $content = $this->content($owner, '2026-11-20 09:00:00', 'draft', $this->actor(['is_active' => false]));

        $full = Blade::render('<x-content-flags :content="$content"/>', ['content' => $content]);
        $this->assertStringContainsString('Lewat tenggat', $full);
        $this->assertStringContainsString('Butuh PIC', $full);

        $compact = Blade::render('<x-content-flags :content="$content" compact/>', ['content' => $content]);
        $this->assertStringContainsString('Telat', $compact);
        $this->assertStringContainsString('PIC?', $compact);
        $this->assertStringContainsString('aria-label="Lewat tenggat"', $compact);
        $this->assertStringContainsString('title="Butuh PIC"', $compact);

        $none = Blade::render('<x-content-flags :content="$content"/>', ['content' => $this->content($owner, '2026-11-30 09:00:00')]);
        $this->assertStringNotContainsString('flag-', $none);
    }

    public function test_detail_page_shows_flags(): void
    {
        $owner = $this->actor();
        $content = $this->content($owner, '2026-11-20 09:00:00');

        $this->actingAs($owner)->get(route('contents.show', $content))->assertOk()->assertSee('Lewat tenggat')->assertDontSee('Butuh PIC');
    }
}
