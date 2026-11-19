<?php

namespace App\Services;

use App\Models\MemberActivationLink;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberActivationService
{
    public function issue(User $actor, User $member): array
    {
        return DB::transaction(function () use ($actor, $member) {
            $target = $this->lockAndAuthorize($actor, $member);
            if (! $target->is_active || filled($target->email)) {
                throw ValidationException::withMessages(['activation' => 'Link hanya tersedia untuk member aktif yang belum memiliki email login.']);
            }
            $this->revokeOutstanding($target);
            $token = Str::random(64);
            $link = MemberActivationLink::forceCreate([
                'user_id' => $target->id, 'created_by' => $actor->id, 'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(24),
            ]);

            // Only this response receives the raw token; the activation table stores its hash.
            return ['link' => $link, 'token' => $token];
        }, 3);
    }

    public function revoke(User $actor, User $member): void
    {
        DB::transaction(function () use ($actor, $member) {
            $target = $this->lockAndAuthorize($actor, $member);
            $this->revokeOutstanding($target);
        }, 3);
    }

    public function resolve(string $token): ?MemberActivationLink
    {
        $link = MemberActivationLink::with('user')->where('token_hash', hash('sha256', $token))->first();

        return $link?->isAvailable() ? $link : null;
    }

    public function activate(string $token, array $data): ?User
    {
        $hash = hash('sha256', $token);
        $candidate = MemberActivationLink::where('token_hash', $hash)->first();
        if (! $candidate) {
            return null;
        }
        try {
            return DB::transaction(function () use ($candidate, $hash, $data) {
                // Same lock order as issuing/revoking: user first, then activation records.
                $user = User::withTrashed()->lockForUpdate()->find($candidate->user_id);
                $link = MemberActivationLink::whereKey($candidate->id)->where('token_hash', $hash)->lockForUpdate()->first();
                if (! $user || ! $link) {
                    return null;
                }
                $link->setRelation('user', $user);
                if (! $link->isAvailable()) {
                    return null;
                }
                $user->forceFill([
                    'email' => $data['email'], 'password' => Hash::make($data['password']),
                    'email_verified_at' => null, 'remember_token' => Str::random(60),
                ])->save();
                $link->used_at = now();
                $link->save();
                $this->revokeOutstanding($user);

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (User::withTrashed()->where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'Email sudah digunakan. Gunakan email lain.']);
            }
            throw $exception;
        }
    }

    private function lockAndAuthorize(User $actor, User $member): User
    {
        $users = User::whereIn('id', [$actor->id, $member->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $actor = $users->get($actor->id);
        $target = $users->get($member->id);
        abort_unless($actor && filled($actor->email), 403);
        Gate::forUser($actor)->authorize('manage-members');
        abort_unless($target && in_array($target->role, ['member', 'coordinator'], true), 404);

        return $target;
    }

    private function revokeOutstanding(User $member): void
    {
        $member->activationLinks()->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
