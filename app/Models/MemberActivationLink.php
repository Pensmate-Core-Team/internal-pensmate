<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberActivationLink extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isAvailable(): bool
    {
        $user = $this->user;

        return $this->used_at === null && $this->revoked_at === null && $this->expires_at->isFuture()
            && $user && ! $user->trashed() && $user->is_active && ! filled($user->email)
            && in_array($user->role, ['member', 'coordinator'], true);
    }
}
