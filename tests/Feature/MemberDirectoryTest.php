<?php

namespace Tests\Feature;

use App\Actions\Members\ImportGenerationMembers;
use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MemberDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private array $csvFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        foreach ($this->csvFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function csv(int $generation, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pensmate-member-test-');
        $this->csvFiles[] = $path;
        $file = fopen($path, 'w');
        fputcsv($file, ['No', 'Nama', $generation === 1 ? 'Kelas' : 'Program Studi'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($file, $row, ',', '"', '');
        }
        fclose($file);

        return $path;
    }

    public function test_admin_is_excluded_from_grid_counts_and_search(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['role' => 'member', 'generation' => 1]);
        $this->actingAs($admin)->get('/members?role=admin')->assertOk()
            ->assertSee('member-grid', false)->assertViewHas('total', 1)->assertViewHas('activeCount', 1)
            ->assertViewHas('members', fn ($members) => $members->pluck('id')->all() === [$member->id]);
        $this->get('/members?q='.urlencode($admin->name))->assertViewHas('members', fn ($members) => $members->total() === 0);
    }

    public function test_admin_targets_cannot_be_viewed_edited_updated_or_deleted_through_member_routes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('members.show', $admin))->assertNotFound();
        $this->get(route('members.edit', $admin))->assertNotFound();
        $this->put(route('members.update', $admin), [])->assertNotFound();
        $this->delete(route('members.destroy', $admin))->assertNotFound();
        $this->assertFalse($admin->fresh()->trashed());
    }

    public function test_directory_member_can_be_created_without_email_or_known_password(): void
    {
        $this->actingAs($this->admin())->post('/members', ['name' => 'Anggota Direktori',
            'generation' => 1, 'class_name' => '2 CE B', 'is_active' => 1])->assertSessionHasNoErrors();
        $member = User::where('name', 'Anggota Direktori')->firstOrFail();
        $this->assertNull($member->email);
        $this->assertSame('member', $member->role);
        $this->assertNotEmpty($member->password);
        $this->assertFalse(Hash::check('password', $member->password));
        $this->get(route('members.show', $member))->assertOk()->assertSee('Belum dibuat')->assertSee('2 CE B');
    }

    public function test_member_module_rejects_role_promotion(): void
    {
        $this->actingAs($this->admin())->post('/members', ['name' => 'Tidak Boleh Admin', 'role' => 'admin', 'is_active' => 1])
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['name' => 'Tidak Boleh Admin']);
    }

    public function test_email_requires_a_password_when_enabling_login_for_imported_member(): void
    {
        $member = User::factory()->create(['email' => null, 'role' => 'member', 'generation' => 2]);
        $data = ['name' => $member->name, 'email' => 'anggota@example.test', 'is_active' => 1];
        $this->actingAs($this->admin())->put(route('members.update', $member), $data)->assertSessionHasErrors('password');
        $this->put(route('members.update', $member), $data + ['password' => 'Password12345', 'password_confirmation' => 'Password12345'])
            ->assertSessionHasNoErrors()->assertRedirect(route('members.show', $member));
        $this->assertTrue(Hash::check('Password12345', $member->fresh()->password));
    }

    public function test_filters_search_academic_fields_and_keep_member_only_counts(): void
    {
        $one = User::factory()->create(['role' => 'member', 'generation' => 1, 'class_name' => '2 CE B']);
        User::factory()->create(['role' => 'member', 'generation' => 2, 'study_program' => 'D4 Sains Data Terapan', 'is_active' => false]);
        $this->actingAs($this->admin())->get('/members?generation=1&q=CE')->assertOk()
            ->assertViewHas('members', fn ($members) => $members->total() === 1 && $members->first()->id === $one->id);
        $this->get('/members?generation=2&active=0&q=Sains')->assertViewHas('members', fn ($members) => $members->total() === 1);
        $this->get('/members?generation=0')->assertSessionHasErrors('generation');
    }

    public function test_archive_preserves_content_references_and_prevents_member_login(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['role' => 'member']);
        $content = new Content(['title' => 'Histori anggota', 'platform' => 'Instagram', 'assigned_to' => $member->id]);
        $content->created_by = $member->id;
        $content->status = 'draft';
        $content->save();
        $reference = $content->references()->create(['title' => 'Sumber histori', 'url' => 'https://example.org', 'created_by' => $member->id]);
        $this->actingAs($admin)->delete(route('members.destroy', $member))->assertRedirect(route('members.index'));
        $this->assertSoftDeleted('users', ['id' => $member->id]);
        $this->assertFalse(User::withTrashed()->findOrFail($member->id)->is_active);
        $this->assertSame($member->id, $content->fresh()->creator->id);
        $this->assertSame($member->id, $content->fresh()->assignee->id);
        $this->assertSame($member->id, $reference->fresh()->creator->id);
        $this->get(route('contents.show', $content))->assertOk()->assertSee($member->name);
        $this->get(route('members.show', $member))->assertNotFound();
        $this->post('/logout');
        $this->post('/login', ['email' => $member->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_regular_members_cannot_use_directory_crud(): void
    {
        $member = User::factory()->create(['role' => 'member', 'is_active' => true]);
        $this->actingAs($member)->get('/members')->assertForbidden();
        $this->get(route('members.show', $member))->assertForbidden();
        $this->post('/members', [])->assertForbidden();
        $this->put(route('members.update', $member), [])->assertForbidden();
        $this->delete(route('members.destroy', $member))->assertForbidden();
    }

    public function test_import_maps_both_generations_without_email_or_admin_changes(): void
    {
        $admin = $this->admin();
        $original = $admin->only('name', 'email', 'password', 'role', 'is_active');
        $result = app(ImportGenerationMembers::class)->handle([
            1 => $this->csv(1, [[1, 'Anggota Satu', '2 D4 IT B']]),
            2 => $this->csv(2, [[1, 'Anggota Dua', 'D4 Teknik Komputer']]),
        ]);
        $this->assertSame(2, $result['created']);
        $this->assertDatabaseHas('users', ['name' => 'Anggota Satu', 'generation' => 1, 'class_name' => '2 D4 IT B', 'email' => null, 'role' => 'member']);
        $this->assertDatabaseHas('users', ['name' => 'Anggota Dua', 'generation' => 2, 'study_program' => 'D4 Teknik Komputer', 'email' => null]);
        $this->assertSame($original, $admin->fresh()->only('name', 'email', 'password', 'role', 'is_active'));
        $this->actingAs(User::where('name', 'Anggota Satu')->firstOrFail())->get('/contents')->assertRedirect('/login');
    }

    public function test_import_retries_preserve_edits_and_never_restore_archived_members(): void
    {
        $sources = [1 => $this->csv(1, [[1, 'Anggota Satu', '2 CE B']]), 2 => $this->csv(2, [[1, 'Anggota Dua', 'D4 Teknik Komputer']])];
        $import = app(ImportGenerationMembers::class);
        $import->handle($sources);
        $one = User::where('generation', 1)->firstOrFail();
        $one->update(['name' => 'Nama Dikoreksi']);
        $two = User::where('generation', 2)->firstOrFail();
        $two->delete();
        $result = $import->handle($sources);
        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame('Nama Dikoreksi', $one->fresh()->name);
        $this->assertSoftDeleted('users', ['id' => $two->id]);
        $this->assertSame(2, User::withTrashed()->count());
    }

    public function test_invalid_second_csv_leaves_first_generation_unimported(): void
    {
        try {
            app(ImportGenerationMembers::class)->handle([1 => $this->csv(1, [[1, 'Anggota Satu', '2 CE B']]),
                2 => $this->csv(2, [[1, 'Anggota Dua', 'D4 Teknik Komputer'], [1, 'Nomor Duplikat', 'D4 Teknik Elektro']])]);
            $this->fail('Duplicate CSV numbers must fail before any writes.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_dry_run_command_never_persists_members(): void
    {
        $this->artisan('pensmate:import-members', ['gen1' => $this->csv(1, [[1, 'Anggota Satu', '2 CE B']]),
            'gen2' => $this->csv(2, [[1, 'Anggota Dua', 'D4 Teknik Komputer']]), '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
    }
}
