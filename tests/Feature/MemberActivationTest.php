<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\MemberActivationLink;
use App\Models\User;
use App\Services\MemberActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create([...['role' => 'member', 'is_active' => true, 'email' => null, 'generation' => 1], ...$attributes]);
    }

    private function issue(User $member, ?User $admin = null): array
    {
        return app(MemberActivationService::class)->issue($admin ?? $this->admin(), $member);
    }

    private function data(array $attributes = []): array
    {
        return [...['email' => 'new.member@example.test', 'password' => 'SecureMember12345', 'password_confirmation' => 'SecureMember12345'], ...$attributes];
    }

    private function url(string $token): string
    {
        return route('activation.show', ['token' => $token]);
    }

    public function test_admin_can_issue_link_but_raw_token_is_not_saved_or_shown_again(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->actingAs($admin)->get(route('members.show', $member))->assertOk()->assertSee('Buat link aktivasi');
        $response = $this->post(route('members.activation.store', $member))->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $url = $response->viewData('activationUrl');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $link = MemberActivationLink::firstOrFail();
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $link->getRawOriginal('token_hash'));
        $this->assertSame($admin->id, $link->created_by);
        $this->assertNull($link->used_at);
        $this->assertStringNotContainsString($token, json_encode($link->getAttributes()));
        $this->assertStringNotContainsString($token, serialize(session()->all()));
        $this->get(route('members.show', $member))->assertOk()->assertSee('Buat link baru')->assertDontSee($token);
    }

    public function test_activation_updates_existing_member_only_and_new_credentials_can_login(): void
    {
        $admin = $this->admin();
        $member = $this->member(['name' => 'Anggota Terdaftar', 'class_name' => '2 CE B']);
        $content = new Content(['title' => 'Riwayat anggota', 'platform' => 'Instagram', 'assigned_to' => $member->id]);
        $content->forceFill(['created_by' => $admin->id, 'status' => 'draft'])->save();
        $issued = $this->issue($member, $admin);
        $url = $this->url($issued['token']);
        $this->get($url)->assertOk()->assertSee('Anggota Terdaftar')->assertSee('Aktifkan akun');
        $this->post($url, $this->data(['email' => ' New.Member@Example.test ', 'role' => 'admin',
            'is_active' => false, 'name' => 'Nama palsu', 'generation' => 99, 'user_id' => $admin->id]))
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $member->refresh();
        $this->assertSame('new.member@example.test', $member->email);
        $this->assertTrue(Hash::check('SecureMember12345', $member->password));
        $this->assertSame('member', $member->role);
        $this->assertTrue($member->is_active);
        $this->assertSame('Anggota Terdaftar', $member->name);
        $this->assertSame(1, $member->generation);
        $this->assertSame('2 CE B', $member->class_name);
        $this->assertNull($member->email_verified_at);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('contents', ['id' => $content->id, 'assigned_to' => $member->id]);
        $this->assertNotNull($issued['link']->fresh()->used_at);
        $this->get(route('login'))->assertSee('Akun berhasil diaktifkan');
        $this->post(route('login.store'), ['email' => 'NEW.MEMBER@EXAMPLE.TEST', 'password' => 'SecureMember12345'])
            ->assertRedirect(route('contents.index'));
        $this->assertAuthenticatedAs($member);
        $this->get(route('members.index'))->assertForbidden();
    }

    public function test_used_link_cannot_be_replayed_to_change_credentials(): void
    {
        $member = $this->member();
        $issued = $this->issue($member);
        $url = $this->url($issued['token']);
        $this->post($url, $this->data())->assertRedirect(route('login'));
        $password = $member->fresh()->password;
        $this->post($url, $this->data(['email' => 'different@example.test']))->assertStatus(410);
        $this->get($url)->assertStatus(410)->assertSee('Link tidak berlaku');
        $this->assertSame('new.member@example.test', $member->fresh()->email);
        $this->assertSame($password, $member->fresh()->password);
    }

    public function test_link_preview_get_does_not_consume_or_activate_account(): void
    {
        $member = $this->member();
        $issued = $this->issue($member);
        for ($i = 0; $i < 2; $i++) {
            $this->get($this->url($issued['token']))->assertOk()
                ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        $this->assertNull($member->fresh()->email);
        $this->assertNull($issued['link']->fresh()->used_at);
    }

    public function test_expiry_is_24_hours_and_expired_link_does_not_change_member(): void
    {
        $member = $this->member();
        $issued = $this->issue($member);
        $this->travel(24)->hours();
        $this->get($this->url($issued['token']))->assertStatus(410);
        $this->post($this->url($issued['token']), $this->data())->assertStatus(410);
        $this->assertNull($member->fresh()->email);
        $this->assertNull($issued['link']->fresh()->used_at);
    }

    public function test_reissue_invalidates_old_link_and_only_new_link_works(): void
    {
        $member = $this->member();
        $admin = $this->admin();
        $old = $this->issue($member, $admin);
        $new = $this->issue($member, $admin);
        $this->assertNotSame($old['token'], $new['token']);
        $this->assertNotNull($old['link']->fresh()->revoked_at);
        $this->get($this->url($old['token']))->assertStatus(410);
        $this->get($this->url($new['token']))->assertOk();
        $this->assertDatabaseCount('member_activation_links', 2);
    }

    public function test_admin_can_revoke_link(): void
    {
        $member = $this->member();
        $admin = $this->admin();
        $issued = $this->issue($member, $admin);
        $this->actingAs($admin)->delete(route('members.activation.destroy', $member))->assertRedirect(route('members.show', $member));
        $this->assertNotNull($issued['link']->fresh()->revoked_at);
        $this->post('/logout');
        $this->get($this->url($issued['token']))->assertStatus(410);
    }

    public function test_deactivation_revokes_link_even_if_member_is_reactivated_later(): void
    {
        $member = $this->member();
        $admin = $this->admin();
        $issued = $this->issue($member, $admin);
        $this->actingAs($admin)->put(route('members.update', $member), ['name' => $member->name, 'is_active' => 0])
            ->assertSessionHasNoErrors();
        $this->put(route('members.update', $member), ['name' => $member->name, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull($issued['link']->fresh()->revoked_at);
        $this->post('/logout');
        $this->get($this->url($issued['token']))->assertStatus(410);
    }

    public function test_archive_revokes_link_and_preserves_activation_history(): void
    {
        $member = $this->member();
        $admin = $this->admin();
        $issued = $this->issue($member, $admin);
        $this->actingAs($admin)->delete(route('members.destroy', $member))->assertRedirect(route('members.index'));
        $this->assertSoftDeleted('users', ['id' => $member->id]);
        $this->assertNotNull($issued['link']->fresh()->revoked_at);
        $this->assertDatabaseCount('member_activation_links', 1);
        $this->post('/logout');
        $this->get($this->url($issued['token']))->assertStatus(410);
    }

    public function test_manual_login_setup_invalidates_pending_activation(): void
    {
        $member = $this->member();
        $admin = $this->admin();
        $issued = $this->issue($member, $admin);
        $this->actingAs($admin)->put(route('members.update', $member), ['name' => $member->name,
            'email' => 'manual@example.test', 'is_active' => 1, 'password' => 'ManualPassword123', 'password_confirmation' => 'ManualPassword123'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($issued['link']->fresh()->revoked_at);
        $this->post('/logout');
        $this->post($this->url($issued['token']), $this->data())->assertStatus(410);
        $this->assertSame('manual@example.test', $member->fresh()->email);
        $this->assertTrue(Hash::check('ManualPassword123', $member->fresh()->password));
    }

    public function test_member_and_coordinator_cannot_issue_or_revoke_links(): void
    {
        $member = $this->member();
        foreach (['member', 'coordinator'] as $role) {
            $actor = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($actor)->post(route('members.activation.store', $member))->assertForbidden();
            $this->delete(route('members.activation.destroy', $member))->assertForbidden();
        }
        $this->assertDatabaseCount('member_activation_links', 0);
    }

    public function test_issuing_requires_active_member_without_login_and_never_targets_admin(): void
    {
        $admin = $this->admin();
        $inactive = $this->member(['is_active' => false]);
        $existing = $this->member(['email' => 'already@example.test']);
        $this->actingAs($admin)->post(route('members.activation.store', $inactive))->assertSessionHasErrors('activation');
        $this->post(route('members.activation.store', $existing))->assertSessionHasErrors('activation');
        $this->post(route('members.activation.store', $admin))->assertNotFound();
        $this->assertDatabaseCount('member_activation_links', 0);
    }

    public function test_fresh_admin_authorization_is_checked_inside_issue_transaction(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $this->actingAs($admin);
        DB::table('users')->where('id', $admin->id)->update(['role' => 'member']);
        $this->post(route('members.activation.store', $member))->assertForbidden();
        $this->assertDatabaseCount('member_activation_links', 0);
    }

    public function test_duplicate_email_and_weak_password_do_not_consume_link(): void
    {
        $member = $this->member();
        $issued = $this->issue($member);
        $other = User::factory()->create();
        $url = $this->url($issued['token']);
        $this->from($url)->post($url, $this->data(['email' => $other->email]))->assertSessionHasErrors('email');
        $this->from($url)->post($url, $this->data(['password' => 'weak', 'password_confirmation' => 'mismatch']))
            ->assertSessionHasErrors('password');
        $this->assertNull($member->fresh()->email);
        $this->assertNull($issued['link']->fresh()->used_at);
        $this->get($url)->assertOk();
    }

    public function test_unsupported_passwords_are_rejected_without_bcrypt_errors(): void
    {
        $member = $this->member();
        $issued = $this->issue($member);
        foreach ([str_repeat('Long1234', 10), "Password123\0unsafe"] as $password) {
            $this->post($this->url($issued['token']), $this->data(['password' => $password, 'password_confirmation' => $password]))
                ->assertSessionHasErrors('password');
        }
        $this->assertNull($issued['link']->fresh()->used_at);
    }

    public function test_malformed_email_never_breaks_form_or_reflects_password(): void
    {
        $member = $this->member();
        $issued = $this->issue($member);
        $url = $this->url($issued['token']);
        $this->from($url)->post($url, $this->data(['email' => ['not-a-string'], 'password' => 'NoNumbersPassword',
            'password_confirmation' => 'different']))->assertSessionHasErrors(['email', 'password']);
        $this->get($url)->assertOk()->assertDontSee('value="NoNumbersPassword"', false)->assertDontSee('value="different"', false);
    }

    public function test_unknown_token_does_not_disclose_member_identity(): void
    {
        $member = $this->member(['name' => 'Nama Anggota Privat']);
        $url = $this->url(Str::random(64));
        $this->get($url)->assertStatus(410)->assertSee('Link tidak berlaku')->assertDontSee($member->name)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post($url, $this->data())->assertStatus(410);
        $this->assertNull($member->fresh()->email);
    }

    public function test_existing_logged_in_account_cannot_claim_another_members_activation(): void
    {
        $member = $this->member();
        $admin = $this->admin();
        $issued = $this->issue($member, $admin);
        $url = $this->url($issued['token']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('jendela privat')
            ->assertDontSee('action="'.route('activation.store', ['token' => $issued['token']]).'"', false);
        $this->post($url, $this->data())->assertRedirect(url('/'));
        $this->assertNull($member->fresh()->email);
        $this->assertNull($issued['link']->fresh()->used_at);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_public_activation_attempts_are_rate_limited(): void
    {
        $member = $this->member();
        $issued = $this->issue($member);
        $url = $this->url($issued['token']);
        for ($i = 0; $i < 10; $i++) {
            $this->post($url, ['email' => 'invalid', 'password' => 'short'])->assertSessionHasErrors();
        }
        $this->post($url, $this->data())->assertStatus(429);
        $this->assertNull($member->fresh()->email);
        $this->assertNull($issued['link']->fresh()->used_at);
    }
}
