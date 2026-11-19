<?php

namespace Tests\Feature;

use App\Actions\Content\SaveContent;
use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function actor(string $role = 'member', string $division = 'Media'): User
    {
        return User::factory()->create(['role' => $role, 'division' => $division, 'is_active' => true]);
    }

    private function image(string $name = 'slide.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDx8AAAAASUVORK5CYII='));
    }

    private function draft(User $owner, int $files = 2): Content
    {
        $this->actingAs($owner)->post('/contents', ['title' => 'Carousel Pengujian', 'caption' => "Caption baris 1\nBaris 2: <tag> & teks",
            'platform' => 'Instagram', 'assigned_to' => $owner->id, 'scheduled_at' => '2026-11-20 10:00:00',
            'division' => 'Media', 'media_files' => array_map(fn ($i) => $this->image('slide-'.$i.'.png'), range(1, $files))])
            ->assertSessionHasNoErrors();

        return Content::latest('id')->firstOrFail();
    }

    private function approveBoth(Content $content, User $owner): array
    {
        $admin = $this->actor('admin');
        $coordinator = $this->actor('coordinator', 'Operasional');
        $this->actingAs($owner)->post(route('contents.transition', $content), ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('contents.transition', $content), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('in_review', $content->fresh()->status);
        $this->actingAs($coordinator)->post(route('contents.transition', $content), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('ready', $content->fresh()->status);

        return [$admin, $coordinator];
    }

    public function test_carousel_upload_is_private_and_preserves_primary_compatibility(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner, 3);
        $assets = $content->assets()->get();
        $this->assertCount(3, $assets);
        $this->assertSame([0, 1, 2], $assets->pluck('position')->all());
        $this->assertSame($assets[0]->storage_path, $content->media_path);
        foreach ($assets as $asset) {
            Storage::disk('local')->assertExists($asset->storage_path);
            $this->get(route('contents.assets.show', [$content, $asset]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->get(route('contents.media', $content))->assertOk();
        $this->get(route('contents.edit', $content))->assertOk()->assertSee('data-asset-order', false);
        $this->get(route('contents.show', $content))->assertOk()->assertSee('Salin caption')->assertSee('data-copy-target', false);
    }

    public function test_final_download_needs_both_approvals_and_remains_private(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $asset = $content->assets()->firstOrFail();
        $url = route('contents.assets.download', [$content, $asset]);
        $this->get($url)->assertForbidden();
        $this->approveBoth($content, $owner);
        $this->actingAs($owner)->get($url)->assertDownload($asset->original_name);
        $this->get(route('contents.download', $content))->assertDownload($asset->original_name);
        $this->actingAs($this->actor())->get($url)->assertForbidden();
        $other = $this->draft($this->actor());
        $this->actingAs($owner)->get(route('contents.assets.download', [$content, $other->assets()->firstOrFail()]))->assertNotFound();
        $this->post('/logout');
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_reordering_and_removing_assets_keeps_primary_fields_in_sync(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner, 3);
        $assets = $content->assets()->get();
        $this->put(route('contents.update', $content), ['title' => $content->title, 'platform' => 'Instagram',
            'assigned_to' => $owner->id, 'asset_order' => [$assets[2]->id, $assets[0]->id, $assets[1]->id],
            'remove_assets' => [$assets[1]->id]])->assertSessionHasNoErrors();
        $this->assertSame([$assets[2]->id, $assets[0]->id], $content->assets()->pluck('id')->all());
        $this->assertSame([0, 1], $content->assets()->pluck('position')->all());
        $this->assertSame($assets[2]->storage_path, $content->fresh()->media_path);
    }

    public function test_foreign_asset_order_and_removal_are_rejected(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $other = $this->draft($owner);
        $data = ['title' => $content->title, 'platform' => 'Instagram', 'assigned_to' => $owner->id];
        $id = $other->assets()->firstOrFail()->id;
        $this->put(route('contents.update', $content), $data + ['remove_assets' => [$id]])->assertSessionHasErrors('remove_assets');
        $this->put(route('contents.update', $content), $data + ['asset_order' => [$id]])->assertSessionHasErrors('asset_order');
        $this->assertSame(2, $content->assets()->count());
    }

    public function test_total_file_limit_and_invalid_file_types_do_not_write_extra_assets(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner, 10);
        $this->put(route('contents.update', $content), ['title' => $content->title, 'platform' => 'Instagram',
            'assigned_to' => $owner->id, 'media_files' => [$this->image('extra.png')]])->assertSessionHasErrors('media_files');
        $this->assertSame(10, $content->assets()->count());
        $this->post('/contents', ['title' => 'Bad upload', 'platform' => 'Instagram', 'assigned_to' => $owner->id,
            'media_files' => [UploadedFile::fake()->create('bad.html', 1, 'text/html')]])->assertSessionHasErrors('media_files.0');
        $this->assertDatabaseMissing('contents', ['title' => 'Bad upload']);
    }

    public function test_failed_asset_metadata_rolls_back_new_content_and_cleans_uploaded_files(): void
    {
        $owner = $this->actor();
        $longName = str_repeat('x', 256).'.png';
        try {
            app(SaveContent::class)->handle($owner, null, ['title' => 'Rollback test', 'platform' => 'Instagram', 'assigned_to' => $owner->id],
                [$this->image('valid.png'), $this->image($longName)]);
            $this->fail('An invalid second file must abort all writes.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('contents', ['title' => 'Rollback test']);
            $this->assertDatabaseCount('content_assets', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_same_division_coordinator_cannot_review_and_different_division_can(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $this->post(route('contents.transition', $content), ['action' => 'submit'])->assertSessionHasNoErrors();
        $same = $this->actor('coordinator', '  mEdIa  ');
        $this->actingAs($same)->get(route('contents.show', $content))->assertForbidden();
        $this->post(route('contents.transition', $content), ['action' => 'approve'])->assertForbidden();
        $other = $this->actor('coordinator', 'Operasional');
        $this->actingAs($other)->get(route('contents.show', $content))->assertOk();
        $this->get(route('contents.edit', $content))->assertForbidden();
        $this->post(route('references.store', $content), ['title' => 'Attempt', 'url' => 'https://example.org'])->assertForbidden();
        $this->post(route('contents.transition', $content), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('in_review', $content->fresh()->status);
    }

    public function test_approvals_need_distinct_people_and_duplicate_admin_signoff_is_rejected(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $admin = $this->actor('admin');
        $this->post(route('contents.transition', $content), ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('contents.transition', $content), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->post(route('contents.transition', $content), ['action' => 'approve'])->assertForbidden();
        $this->actingAs($this->actor('admin'))->post(route('contents.transition', $content), ['action' => 'approve'])->assertForbidden();
        $admin->update(['role' => 'coordinator', 'division' => 'Operasional']);
        $this->actingAs($admin->fresh())->post(route('contents.transition', $content), ['action' => 'approve'])->assertForbidden();
        $this->assertSame(1, $content->reviews()->count());
        $this->assertSame('in_review', $content->fresh()->status);
    }

    public function test_revision_requires_two_new_approvals_and_keeps_review_history(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $admin = $this->actor('admin');
        $coordinator = $this->actor('coordinator', 'Operasional');
        $url = route('contents.transition', $content);
        $this->post($url, ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post($url, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->actingAs($coordinator)->post($url, ['action' => 'revise'])->assertSessionHasErrors('review_notes');
        $this->post($url, ['action' => 'revise', 'review_notes' => 'Perbaiki slide kedua'])->assertSessionHasNoErrors();
        $this->assertSame('revision', $content->fresh()->status);
        $this->actingAs($owner)->get(route('contents.edit', $content))->assertOk();
        $this->post($url, ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->assertSame(2, $content->fresh()->revision_number);
        $this->assertFalse($content->fresh()->hasRequiredApprovals());
        $this->actingAs($admin)->post($url, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('in_review', $content->fresh()->status);
        $this->actingAs($coordinator)->post($url, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('ready', $content->fresh()->status);
        $this->assertSame(4, $content->reviews()->count());
        $this->actingAs($owner)->get(route('contents.show', $content))->assertOk()->assertSee('Perbaiki slide kedua');
    }

    public function test_materials_and_references_lock_during_review_and_after_approval(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $reference = $content->references()->create(['title' => 'Inspiration', 'url' => 'https://example.org', 'created_by' => $owner->id]);
        $this->post(route('contents.transition', $content), ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->put(route('contents.update', $content), [])->assertForbidden();
        $this->put(route('references.update', [$content, $reference]), ['title' => 'Changed', 'url' => 'https://example.net'])->assertForbidden();
        $this->delete(route('references.destroy', [$content, $reference]))->assertForbidden();
    }

    public function test_unavailable_media_or_missing_division_blocks_submission(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        Storage::disk('local')->delete($content->media_path);
        $this->post(route('contents.transition', $content), ['action' => 'submit'])->assertSessionHasErrors('action');
        $this->assertSame(0, $content->reviews()->count());
        $this->assertSame('draft', $content->fresh()->status);
    }

    public function test_admin_can_assign_coordinator_but_member_cannot_promote_self(): void
    {
        $admin = $this->actor('admin');
        $member = $this->actor();
        $data = ['name' => $member->name, 'email' => $member->email, 'is_active' => 1, 'role' => 'coordinator', 'division' => 'Operasional'];
        $this->actingAs($member)->put(route('members.update', $member), $data)->assertForbidden();
        $this->actingAs($admin)->put(route('members.update', $member), $data)->assertSessionHasNoErrors();
        $this->assertSame('coordinator', $member->fresh()->role);
        $this->get('/members')->assertViewHas('members', fn ($members) => $members->contains('id', $member->id));
        $this->put(route('members.update', $member), [...$data, 'division' => ''])->assertSessionHasErrors('division');
    }

    public function test_coordinator_can_approve_before_admin_and_order_does_not_change_requirement(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $url = route('contents.transition', $content);
        $this->post($url, ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->actingAs($this->actor('coordinator', 'Operasional'))->post($url, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('in_review', $content->fresh()->status);
        $this->actingAs($this->actor('admin'))->post($url, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('ready', $content->fresh()->status);
    }

    public function test_non_admin_cannot_forge_assignment_or_division_through_save_action(): void
    {
        $owner = $this->actor();
        $other = $this->actor();
        $content = app(SaveContent::class)->handle($owner, null, ['title' => 'Protected assignment', 'platform' => 'Instagram',
            'assigned_to' => $other->id, 'division' => 'Forged division'], []);
        $this->assertSame($owner->id, $content->assigned_to);
        $this->assertSame('Media', $content->division);
    }

    public function test_removing_all_assets_makes_draft_ineligible_for_review(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $this->put(route('contents.update', $content), ['title' => $content->title, 'caption' => $content->caption, 'platform' => 'Instagram',
            'scheduled_at' => $content->scheduled_at->format('Y-m-d H:i:s'), 'assigned_to' => $owner->id,
            'remove_assets' => $content->assets()->pluck('id')->all()])->assertSessionHasNoErrors();
        $this->assertNull($content->fresh()->media_path);
        $this->post(route('contents.transition', $content), ['action' => 'submit'])->assertSessionHasErrors('action');
    }
}
