<?php

namespace App\Policies;

use App\Models\Content;
use App\Models\User;

class ContentPolicy
{
    public function view(User $user, Content $content): bool
    {
        if (! $user->is_active || ! filled($user->email)) {
            return false;
        }

        return $user->isAdmin() || $content->created_by === $user->id || $content->assigned_to === $user->id
            || $this->canCoordinate($user, $content)
            || ($user->role === 'coordinator' && $content->reviews()->where('reviewer_id', $user->id)->exists());
    }

    public function update(User $user, Content $content): bool
    {
        return $user->is_active && filled($user->email) && in_array($content->status, ['draft', 'revision'], true)
            && ($user->isAdmin() || $content->created_by === $user->id || $content->assigned_to === $user->id);
    }

    public function review(User $user, Content $content): bool
    {
        return $user->is_active && filled($user->email) && $content->status === 'in_review'
            && ($user->isAdmin() || $this->canCoordinate($user, $content))
            && ! $content->reviews()->where('revision_number', $content->revision_number)
                ->where(fn ($query) => $query->where('reviewer_id', $user->id)->orWhere('reviewer_role', $user->role))->exists();
    }

    public function publish(User $user, Content $content): bool
    {
        return $this->view($user, $content) && $content->status === 'ready' && $content->hasRequiredApprovals()
            && ($user->isAdmin() || $content->created_by === $user->id || $content->assigned_to === $user->id);
    }

    public function download(User $user, Content $content): bool
    {
        return $this->view($user, $content) && in_array($content->status, ['ready', 'published'], true)
            && $content->hasRequiredApprovals();
    }

    private function canCoordinate(User $user, Content $content): bool
    {
        return $user->role === 'coordinator' && $content->status === 'in_review'
            && $content->created_by !== $user->id && $content->assigned_to !== $user->id
            && User::divisionKey($user->division) !== '' && User::divisionKey($content->division) !== ''
            && User::divisionKey($user->division) !== User::divisionKey($content->division);
    }
}
