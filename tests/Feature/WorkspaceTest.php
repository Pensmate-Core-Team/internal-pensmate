<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function user(string $role = 'member'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true, 'division' => $role === 'coordinator' ? 'Operasional' : 'Media']);
    }

    private function content(User $owner, string $status = 'draft'): Content
    {
        $content = new Content(['title' => 'Konten pengujian', 'caption' => 'Caption lengkap.', 'platform' => 'Instagram', 'assigned_to' => $owner->id, 'scheduled_at' => now()->addDay(), 'division' => 'Media']);
        $content->created_by = $owner->id;
        $content->status = $status;
        $content->media_path = 'content-media/test.png';
        $content->media_name = 'test.png';
        $content->media_mime = 'image/png';
        $content->save();
        Storage::disk('local')->put('content-media/test.png', 'test-image');

        return $content;
    }

    public function test_guests_and_inactive_accounts_cannot_access_workspace(): void
    {
        $this->get('/contents')->assertRedirect('/login');
        $user = $this->user();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get('/contents')->assertRedirect('/login');
    }

    public function test_login_logout_and_inactive_login(): void
    {
        $user = $this->user();
        $user->update(['password' => 'Password12345']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'Password12345'])->assertRedirect('/contents');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $user->update(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Password12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_member_scope_and_admin_member_management(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $admin = $this->user('admin');
        $content = $this->content($owner);
        $this->actingAs($other)->get(route('contents.show', $content))->assertForbidden();
        $this->get('/contents')->assertOk()->assertDontSee('Konten pengujian');
        $this->get('/members')->assertForbidden();
        $this->actingAs($admin)->get('/members')->assertOk();
        $this->get(route('contents.show', $content))->assertOk();
        $this->get('/contents/create')->assertOk();
        $this->get('/members/create')->assertOk();
        $this->get(route('members.edit', $owner))->assertOk();
    }

    public function test_complete_review_revision_and_publication_workflow(): void
    {
        $owner = $this->user();
        $admin = $this->user('admin');
        $content = $this->content($owner);
        $url = route('contents.transition', $content);
        $this->actingAs($owner)->post($url, ['action' => 'approve'])->assertForbidden();
        $this->post($url, ['action' => 'publish', 'published_url' => 'https://instagram.com/p/test'])->assertForbidden();
        $this->post($url, ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->assertSame('in_review', $content->fresh()->status);
        $this->put(route('contents.update', $content), [])->assertForbidden();
        $this->actingAs($admin)->post($url, ['action' => 'revise'])->assertSessionHasErrors('review_notes');
        $this->post($url, ['action' => 'revise', 'review_notes' => 'Perbaiki pembuka.'])->assertSessionHasNoErrors();
        $this->assertSame('revision', $content->fresh()->status);
        $this->actingAs($owner)->post($url, ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post($url, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('in_review', $content->fresh()->status);
        $this->actingAs($this->user('coordinator'))->post($url, ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('ready', $content->fresh()->status);
        $this->actingAs($owner)->post($url, ['action' => 'publish', 'published_url' => 'javascript:alert(1)'])->assertSessionHasErrors('published_url');
        $this->post($url, ['action' => 'publish', 'published_url' => 'https://instagram.com/p/test'])->assertSessionHasNoErrors();
        $this->assertSame('published', $content->fresh()->status);
        $this->assertNotNull($content->fresh()->published_at);
        $this->get(route('contents.show', $content))->assertOk()->assertSee('Buka postingan');
    }

    public function test_submission_requires_complete_material_and_member_cannot_reassign(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->actingAs($owner)->post('/contents', ['title' => 'Draf baru', 'platform' => 'Instagram', 'assigned_to' => $other->id])->assertSessionHasNoErrors();
        $content = Content::firstOrFail();
        $this->assertSame($owner->id, $content->assigned_to);
        $this->post(route('contents.transition', $content), ['action' => 'submit'])->assertSessionHasErrors('action');
        $this->assertSame('draft', $content->fresh()->status);
    }

    public function test_media_upload_is_private_and_can_be_replaced(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $data = ['title' => 'Draf media', 'platform' => 'Instagram', 'assigned_to' => $owner->id];
        $file = UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDx8AAAAASUVORK5CYII='));
        $this->actingAs($owner)->post('/contents', [...$data, 'media' => $file])->assertSessionHasNoErrors();
        $content = Content::firstOrFail();
        $old = $content->media_path;
        Storage::disk('local')->assertExists($old);
        $this->get(route('contents.media', $content))->assertOk();
        $this->actingAs($this->user())->get(route('contents.media', $content))->assertForbidden();
        $replacement = UploadedFile::fake()->createWithContent('replacement.png', file_get_contents($file->getRealPath()));
        $this->actingAs($owner)->put(route('contents.update', $content), [...$data, 'media' => $replacement])->assertSessionHasNoErrors();
        // RefreshDatabase wraps each test in a transaction; afterCommit cleanup runs in production.
        $this->assertNotSame($old, $content->fresh()->media_path);
        Storage::disk('local')->assertExists($content->fresh()->media_path);
        $this->post('/contents', [...$data, 'media' => UploadedFile::fake()->create('bad.html', 1, 'text/html')])->assertSessionHasErrors('media');
    }

    public function test_references_validate_urls_and_nested_ownership(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $content = $this->content($owner);
        $another = $this->content($owner);
        $url = route('references.store', $content);
        $this->actingAs($other)->post($url, ['title' => 'Sumber', 'url' => 'https://example.org'])->assertForbidden();
        $this->actingAs($owner)->post($url, ['title' => 'Sumber', 'url' => 'javascript:alert(1)'])->assertSessionHasErrorsIn('newReference', 'url');
        $this->post($url, ['title' => 'Sumber', 'url' => 'https://example.org'])->assertSessionHasNoErrors();
        $reference = $content->references()->firstOrFail();
        $this->from(route('contents.show', $content))->put(route('references.update', [$content, $reference]), ['title' => 'Judul yang tetap tersimpan', 'url' => 'bukan-url', 'notes' => 'Catatan percobaan'])->assertSessionHasErrorsIn('reference-'.$reference->id, 'url');
        $this->get(route('contents.show', $content))->assertOk()
            ->assertSee('id="edit-reference-'.$reference->id.'"  open', false)
            ->assertSee('value="Judul yang tetap tersimpan"', false)
            ->assertSee('value="bukan-url"', false)
            ->assertSee('Catatan percobaan');
        $this->put(route('references.update', [$another, $reference]), ['title' => 'Baru', 'url' => 'https://example.org'])->assertNotFound();
        $this->put(route('references.update', [$content, $reference]), ['title' => 'Baru', 'url' => 'https://example.org/new'])->assertSessionHasNoErrors();
        $this->assertSame('Baru', $reference->fresh()->title);
        $this->delete(route('references.destroy', [$content, $reference]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('content_references', ['id' => $reference->id]);
    }

    public function test_member_creation_deactivation_and_self_protection(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/members', ['name' => 'Anggota baru', 'email' => 'new@example.org', 'role' => 'member', 'is_active' => '1', 'password' => 'Password12345', 'password_confirmation' => 'Password12345'])->assertSessionHasNoErrors();
        $member = User::where('email', 'new@example.org')->firstOrFail();
        $content = $this->content($member);
        $this->put(route('members.update', $member), ['name' => $member->name, 'email' => $member->email, 'role' => 'member', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->is_active);
        $this->assertDatabaseHas('contents', ['id' => $content->id, 'assigned_to' => $member->id]);
        $this->put(route('members.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'member', 'is_active' => '0'])->assertNotFound();
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_filters_and_invalid_status(): void
    {
        $admin = $this->user('admin');
        $content = $this->content($admin, 'ready');
        $this->actingAs($admin)->get('/contents?status=ready&q=Konten&platform=Instagram')->assertOk()->assertSee($content->title);
        $this->get('/contents?status=draft')->assertOk()->assertDontSee($content->title);
        $this->get('/contents?status=invalid')->assertSessionHasErrors('status');
        $this->get('/members?active=0')->assertOk()->assertSee('Member tidak ditemukan');
    }
}
