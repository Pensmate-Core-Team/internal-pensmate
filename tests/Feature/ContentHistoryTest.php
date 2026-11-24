<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ContentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function actor(string $role = 'member', string $division = 'Media', array $attributes = []): User
    {
        return User::factory()->create(['role' => $role, 'division' => $division, 'is_active' => true, ...$attributes]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('slide.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDx8AAAAASUVORK5CYII='));
    }

    private function draft(User $owner, array $overrides = []): Content
    {
        $this->actingAs($owner)->post('/contents', [...['title' => 'Konten Riwayat', 'caption' => 'Caption',
            'platform' => 'Instagram', 'assigned_to' => $owner->id, 'scheduled_at' => '2026-11-20 09:00:00',
            'division' => 'Media', 'media_files' => [$this->image()]], ...$overrides])->assertSessionHasNoErrors();

        return Content::latest('id')->firstOrFail();
    }

    private function statusRows(Content $content): array
    {
        return ContentHistory::where('content_id', $content->id)->where('field', 'status')->orderBy('id')->get()
            ->map(fn (ContentHistory $row) => [$row->old_value, $row->new_value, $row->actor_id])->all();
    }

    public function test_creating_content_records_initial_status(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);

        $rows = ContentHistory::where('content_id', $content->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('status', $rows[0]->field);
        $this->assertNull($rows[0]->old_value);
        $this->assertSame('draft', $rows[0]->new_value);
        $this->assertSame($owner->id, $rows[0]->actor_id);
    }

    public function test_full_workflow_records_each_status_change_with_actor_and_revision_note(): void
    {
        $owner = $this->actor();
        $admin = $this->actor('admin');
        $coordinator = $this->actor('coordinator', 'Operasional');
        $content = $this->draft($owner);
        $transition = fn (User $user, array $data) => $this->actingAs($user)
            ->post(route('contents.transition', $content), $data)->assertSessionHasNoErrors();

        $transition($owner, ['action' => 'submit']);
        $transition($admin, ['action' => 'revise', 'review_notes' => 'Perbaiki warna']);
        $transition($owner, ['action' => 'submit']);
        $transition($admin, ['action' => 'approve']);
        $transition($coordinator, ['action' => 'approve']);
        $transition($owner, ['action' => 'publish', 'published_url' => 'https://instagram.com/p/abc']);

        $this->assertSame([
            [null, 'draft', $owner->id],
            ['draft', 'in_review', $owner->id],
            ['in_review', 'revision', $admin->id],
            ['revision', 'in_review', $owner->id],
            ['in_review', 'ready', $coordinator->id],
            ['ready', 'published', $owner->id],
        ], $this->statusRows($content));
        $revision = ContentHistory::where('content_id', $content->id)->where('new_value', 'revision')->firstOrFail();
        $this->assertSame('Perbaiki warna', $revision->note);
        $this->assertNull(ContentHistory::where('content_id', $content->id)->where('new_value', 'ready')->value('note'));
    }

    public function test_schedule_and_assignee_changes_are_recorded(): void
    {
        $admin = $this->actor('admin');
        $first = $this->actor();
        $second = $this->actor();
        $content = $this->draft($admin, ['assigned_to' => $first->id]);

        $this->actingAs($admin)->put(route('contents.update', $content), ['title' => 'Konten Riwayat', 'platform' => 'Instagram',
            'assigned_to' => $second->id, 'scheduled_at' => '2026-11-21 10:30:00', 'division' => 'Media'])->assertSessionHasNoErrors();

        $schedule = ContentHistory::where('content_id', $content->id)->where('field', 'scheduled_at')->sole();
        $this->assertSame('2026-11-20T09:00:00+07:00', $schedule->old_value);
        $this->assertSame('2026-11-21T10:30:00+07:00', $schedule->new_value);
        $this->assertSame($admin->id, $schedule->actor_id);
        $assignee = ContentHistory::where('content_id', $content->id)->where('field', 'assigned_to')->sole();
        $this->assertSame((string) $first->id, $assignee->old_value);
        $this->assertSame((string) $second->id, $assignee->new_value);
    }

    public function test_untracked_field_changes_are_not_recorded(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $before = ContentHistory::count();

        $this->actingAs($owner)->put(route('contents.update', $content), ['title' => 'Judul Baru', 'caption' => 'Caption baru',
            'platform' => 'Instagram', 'assigned_to' => $owner->id, 'scheduled_at' => '2026-11-20 09:00:00'])->assertSessionHasNoErrors();

        $this->assertSame('Judul Baru', $content->fresh()->title);
        $this->assertSame($before, ContentHistory::count());
    }

    public function test_failed_transaction_leaves_no_history(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);

        try {
            DB::transaction(function () use ($content) {
                $content->status = 'in_review';
                $content->save();
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
        }

        $this->assertFalse(ContentHistory::where('content_id', $content->id)->where('new_value', 'in_review')->exists());
    }

    private function row(Content $content, ?User $actor, string $field, ?string $old, ?string $new, string $at, ?string $note = null): void
    {
        ContentHistory::forceCreate(['content_id' => $content->id, 'actor_id' => $actor?->id, 'field' => $field,
            'old_value' => $old, 'new_value' => $new, 'note' => $note, 'created_at' => $at]);
    }

    public function test_detail_page_shows_history_newest_first_with_readable_values(): void
    {
        $owner = $this->actor('member', 'Media', ['name' => 'Sinta']);
        $bima = $this->actor('member', 'Media', ['name' => 'Bima']);
        $content = $this->draft($owner);
        ContentHistory::query()->delete();
        $this->row($content, $owner, 'status', 'draft', 'in_review', '2026-11-20 08:00:00');
        $this->row($content, $owner, 'scheduled_at', '2026-11-20T09:00:00+07:00', '2026-11-21T10:30:00+07:00', '2026-11-21 08:00:00');
        $this->row($content, $owner, 'assigned_to', (string) $owner->id, (string) $bima->id, '2026-11-22 08:00:00', null);

        $this->actingAs($owner)->get(route('contents.show', $content))->assertOk()
            ->assertSee('Riwayat perubahan')
            ->assertSeeInOrder(['mengganti PIC Sinta → Bima', 'mengubah jadwal 20 Nov 2026 09:00 WIB → 21 Nov 2026 10:30 WIB', 'mengubah status Draf → Dalam review'])
            ->assertSeeText('Sinta mengubah status Draf → Dalam review');
    }

    public function test_archived_actor_and_assignee_names_still_render(): void
    {
        $owner = $this->actor('member', 'Media', ['name' => 'Sinta']);
        $admin = $this->actor('admin', 'Media', ['name' => 'Rani Admin']);
        $bima = $this->actor('member', 'Media', ['name' => 'Bima']);
        $content = $this->draft($owner);
        $this->row($content, $admin, 'assigned_to', (string) $bima->id, (string) $owner->id, '2026-11-22 08:00:00');
        $admin->delete();
        $bima->delete();

        $this->actingAs($owner)->get(route('contents.show', $content))->assertOk()
            ->assertSeeText('Rani Admin mengganti PIC Bima → Sinta');
    }

    public function test_pic_change_names_are_loaded_in_one_query(): void
    {
        $owner = $this->actor('member', 'Media', ['name' => 'Sinta']);
        $content = $this->draft($owner);
        foreach (range(1, 6) as $i) {
            $pic = $this->actor('member', 'Media', ['name' => 'PIC '.$i]);
            $this->row($content, $owner, 'assigned_to', (string) $owner->id, (string) $pic->id, '2026-11-22 08:0'.$i.':00');
        }

        DB::enableQueryLog();
        $this->actingAs($owner)->get(route('contents.show', $content))->assertOk()->assertSeeText('mengganti PIC Sinta → PIC 6');
        $perRowLookups = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "users"')
            && str_contains($query['query'], '= ? limit 1'));

        $this->assertCount(0, $perRowLookups, 'Nama PIC di riwayat harus dimuat sekaligus, bukan per baris.');
    }

    public function test_history_without_actor_is_labelled_sistem(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        $this->row($content, null, 'status', 'draft', 'revision', '2026-11-22 08:00:00', 'Catatan otomatis');

        $this->actingAs($owner)->get(route('contents.show', $content))->assertOk()
            ->assertSeeText('Sistem mengubah status Draf → Perlu revisi')->assertSee('Catatan otomatis');
    }

    public function test_empty_history_shows_honest_message(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        ContentHistory::query()->delete();

        $this->actingAs($owner)->get(route('contents.show', $content))->assertOk()->assertSee('Belum ada perubahan tercatat');
    }

    public function test_change_without_logged_in_user_has_null_actor(): void
    {
        $owner = $this->actor();
        $content = $this->draft($owner);
        auth()->logout();

        $content->forceFill(['status' => 'revision'])->save();

        $row = ContentHistory::where('content_id', $content->id)->where('new_value', 'revision')->sole();
        $this->assertNull($row->actor_id);
    }
}
