<?php

namespace App\Actions\Content;

use App\Models\Content;
use App\Models\ContentReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TransitionContent
{
    public function handle(User $actor, Content $content, array $data): void
    {
        if ($data['action'] === 'revise' && trim($data['review_notes'] ?? '') === '') {
            throw ValidationException::withMessages(['review_notes' => 'Catatan wajib diisi saat meminta revisi.']);
        }
        DB::transaction(function () use ($actor, $content, $data) {
            $item = Content::lockForUpdate()->findOrFail($content->id);
            $actor = User::lockForUpdate()->findOrFail($actor->id);
            switch ($data['action']) {
                case 'submit':
                    Gate::forUser($actor)->authorize('update', $item);
                    $paths = $item->assets()->pluck('storage_path');
                    if ($paths->isEmpty() && $item->media_path) {
                        $paths->push($item->media_path);
                    }
                    if (! filled($item->caption) || ! $item->scheduled_at || ! filled($item->division)
                        || $paths->isEmpty() || $paths->contains(fn ($path) => ! Storage::disk('local')->exists($path))) {
                        throw ValidationException::withMessages(['action' => 'Lengkapi caption, media yang tersedia, jadwal dan divisi sebelum review.']);
                    }
                    $item->revision_number++;
                    $item->status = 'in_review';
                    $item->review_notes = null;
                    break;
                case 'approve':
                case 'revise':
                    Gate::forUser($actor)->authorize('review', $item);
                    ContentReview::forceCreate(['content_id' => $item->id, 'revision_number' => $item->revision_number,
                        'reviewer_id' => $actor->id, 'reviewer_role' => $actor->role,
                        'reviewer_division' => $actor->division,
                        'decision' => $data['action'] === 'approve' ? 'approved' : 'revision_requested',
                        'comment' => $data['review_notes'] ?? null]);
                    $item->review_notes = $data['review_notes'] ?? null;
                    $item->reviewed_by = $actor->id;
                    $item->reviewed_at = now();
                    $item->status = $data['action'] === 'revise' ? 'revision' : ($item->hasRequiredApprovals() ? 'ready' : 'in_review');
                    break;
                case 'publish':
                    Gate::forUser($actor)->authorize('publish', $item);
                    $item->status = 'published';
                    $item->published_url = $data['published_url'];
                    $item->published_at = now();
                    break;
            }
            $item->save();
        }, 3);
    }
}
