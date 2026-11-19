<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimelineIntegrationTest extends TestCase
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

    private function content(User $owner, string $date = '2026-11-20 09:00:00', string $status = 'draft'): Content
    {
        $content = new Content(['title' => 'Konten Timeline', 'platform' => 'Instagram', 'assigned_to' => $owner->id,
            'division' => $owner->division, 'scheduled_at' => $date]);
        $content->forceFill(['created_by' => $owner->id, 'status' => $status])->save();

        return $content;
    }

    private function feed(array $filters = []): string
    {
        return route('timeline.contents', [...['start' => '2026-11-01', 'end' => '2026-11-30'], ...$filters]);
    }

    public function test_feed_includes_references_detail_links_and_private_preview_without_storage_paths(): void
    {
        $owner = $this->actor();
        $content = $this->content($owner);
        $reference = $content->references()->create(['title' => 'Inspirasi', 'url' => 'https://example.org/post',
            'notes' => 'Catatan referensi', 'created_by' => $owner->id]);
        $asset = $content->assets()->create(['storage_path' => 'content-media/private.png', 'original_name' => 'slide.png',
            'mime_type' => 'image/png', 'position' => 0]);
        $this->actingAs($owner)->getJson($this->feed())->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $content->id)->assertJsonPath('data.0.start', '2026-11-20T09:00:00+07:00')
            ->assertJsonPath('data.0.references.0.id', $reference->id)->assertJsonPath('data.0.references.0.url', $reference->url)
            ->assertJsonPath('data.0.references_url', route('contents.show', $content).'#references')
            ->assertJsonPath('data.0.assets.0.preview_url', route('contents.assets.show', [$content, $asset]))
            ->assertJsonPath('data.0.assets.0.download_url', null)
            ->assertDontSee('content-media/private.png')->assertDontSee('password');
    }

    public function test_feed_and_list_apply_the_same_member_scope(): void
    {
        $owner = $this->actor();
        $mine = $this->content($owner);
        $other = $this->content($this->actor());
        $this->actingAs($owner)->getJson($this->feed())->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->get(route('contents.show', $other))->assertForbidden();
        $this->actingAs($this->actor('admin'))->getJson($this->feed())->assertJsonCount(2, 'data');
    }

    public function test_coordinator_feed_only_expands_to_reviewable_other_division_content(): void
    {
        $coordinator = $this->actor('coordinator', 'Operasional');
        $allowed = $this->content($this->actor('member', 'Media'), status: 'in_review');
        $this->content($this->actor('member', 'Operasional'), status: 'in_review');
        $this->content($this->actor('member', 'Media'), status: 'draft');
        $this->actingAs($coordinator)->getJson($this->feed())->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $allowed->id);
        $this->get(route('contents.show', $allowed))->assertOk();
        $this->get('/contents')->assertViewHas('contents', fn ($contents) => $contents->pluck('id')->all() === [$allowed->id]);
    }

    public function test_revoking_coordinator_role_removes_access_to_review_history_of_other_members(): void
    {
        $coordinator = $this->actor('coordinator', 'Operasional');
        $content = $this->content($this->actor(), status: 'in_review');
        $content->forceFill(['revision_number' => 1])->save();
        $this->actingAs($coordinator)->post(route('contents.transition', $content), ['action' => 'approve'])->assertSessionHasNoErrors();
        $coordinator->update(['role' => 'member']);
        $this->actingAs($coordinator->fresh())->get(route('contents.show', $content))->assertForbidden();
        $this->getJson($this->feed())->assertJsonCount(0, 'data');
    }

    public function test_feed_validates_date_ranges_filters_and_pagination(): void
    {
        $this->actingAs($this->actor('admin'));
        $this->getJson(route('timeline.contents'))->assertUnprocessable()->assertJsonValidationErrors(['start', 'end']);
        $this->getJson($this->feed(['start' => '2026-11-30', 'end' => '2026-11-01']))->assertUnprocessable();
        $this->getJson($this->feed(['end' => '2027-05-01']))->assertUnprocessable()->assertJsonValidationErrors('end');
        $this->getJson($this->feed(['start' => 'not-a-date']))->assertUnprocessable();
        $this->getJson($this->feed(['status' => 'invalid']))->assertUnprocessable();
        $this->getJson($this->feed(['page' => 0]))->assertUnprocessable();
    }

    public function test_range_boundaries_filters_and_scheduled_order_are_consistent(): void
    {
        $owner = $this->actor();
        $this->content($owner, '2026-10-31 23:59:59');
        $last = $this->content($owner, '2026-11-30 23:59:59');
        $first = $this->content($owner, '2026-11-01 00:00:00');
        $this->content($owner, '2026-12-01 00:00:00');
        $this->actingAs($owner)->getJson($this->feed(['platform' => 'Instagram', 'status' => 'draft']))
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $last->id);
        $this->getJson($this->feed(['platform' => 'YouTube']))->assertJsonCount(0, 'data');
    }

    public function test_feed_is_paginated_and_does_not_drop_later_events_silently(): void
    {
        $owner = $this->actor();
        $rows = [];
        for ($i = 0; $i < 101; $i++) {
            $rows[] = ['title' => 'Item '.$i, 'platform' => 'Instagram', 'created_by' => $owner->id,
                'assigned_to' => $owner->id, 'status' => 'draft', 'scheduled_at' => '2026-11-20 10:00:00',
                'created_at' => now(), 'updated_at' => now()];
        }
        Content::insert($rows);
        $this->actingAs($owner)->getJson($this->feed())->assertJsonCount(100, 'data')
            ->assertJsonPath('pagination.total', 101)->assertJsonPath('pagination.last_page', 2);
        $this->getJson($this->feed(['page' => 2]))->assertJsonCount(1, 'data')->assertJsonPath('pagination.current_page', 2);
    }

    public function test_legacy_primary_media_is_backfilled_without_moving_or_erasing_fields(): void
    {
        $migration = require database_path('migrations/2026_11_19_000002_add_carousel_and_dual_content_reviews.php');
        $migration->down();
        $owner = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $id = DB::table('contents')->insertGetId([
            'title' => 'Legacy content', 'platform' => 'Instagram', 'created_by' => $owner->id, 'assigned_to' => $owner->id,
            'status' => 'draft', 'media_path' => 'content-media/original.png', 'media_name' => 'original.png', 'media_mime' => 'image/png',
        ]);
        $migration->up();
        $content = Content::findOrFail($id);
        $this->assertSame('content-media/original.png', $content->media_path);
        $this->assertSame('original.png', $content->assets()->firstOrFail()->original_name);
        $this->assertDatabaseCount('content_assets', 1);
    }

    public function test_division_whitespace_normalization_keeps_feed_and_policy_in_agreement(): void
    {
        $owner = $this->actor('member', 'Media  Kreatif');
        $content = $this->content($owner, status: 'in_review');
        $coordinator = $this->actor('coordinator', '  MEDIA   Kreatif  ');
        $this->actingAs($coordinator)->getJson($this->feed())->assertJsonCount(0, 'data');
        $this->get(route('contents.show', $content))->assertForbidden();
    }

    public function test_guests_and_inactive_users_cannot_read_timeline_data(): void
    {
        $this->getJson($this->feed())->assertUnauthorized();
        $inactive = $this->actor();
        $inactive->update(['is_active' => false]);
        $this->actingAs($inactive)->getJson($this->feed())->assertRedirect(route('login'));
    }
}
