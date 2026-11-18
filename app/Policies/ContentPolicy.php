<?php

namespace App\Policies;

use App\Models\Content;
use App\Models\User;

class ContentPolicy
{
    public function view(User $user, Content $content): bool
    {
        return $user->is_active && ($user->isAdmin() || $content->created_by === $user->id || $content->assigned_to === $user->id);
    }

    public function update(User $user, Content $content): bool
    {
        return $this->view($user, $content) && in_array($content->status, ['draft', 'revision']);
    }

    public function review(User $user, Content $content): bool
    {
        return $user->is_active && $user->isAdmin() && $content->status === 'in_review';
    }

    public function publish(User $user, Content $content): bool
    {
        return $this->view($user, $content) && $content->status === 'ready';
    }
}
