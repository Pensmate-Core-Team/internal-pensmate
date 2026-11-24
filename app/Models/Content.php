<?php

namespace App\Models;

use App\Observers\ContentHistoryObserver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[ObservedBy(ContentHistoryObserver::class)]
class Content extends Model
{
    public const STATUSES = ['draft' => 'Draf', 'in_review' => 'Dalam review', 'revision' => 'Perlu revisi', 'ready' => 'Siap upload', 'published' => 'Terunggah'];

    public const PLATFORMS = ['Instagram', 'TikTok', 'YouTube', 'LinkedIn', 'X'];

    protected $fillable = ['title', 'caption', 'platform', 'assigned_to', 'scheduled_at', 'division'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'reviewed_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function setDivisionAttribute(?string $value): void
    {
        $this->attributes['division'] = filled($value) ? Str::squish($value) : null;
    }

    public function assets(): HasMany
    {
        return $this->hasMany(ContentAsset::class)->orderBy('position')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ContentReview::class);
    }

    public function hasRequiredApprovals(): bool
    {
        $reviews = $this->reviews()->where('revision_number', $this->revision_number)->where('decision', 'approved')->get();

        return $reviews->contains('reviewer_role', 'admin') && $reviews->contains('reviewer_role', 'coordinator')
            && $reviews->pluck('reviewer_id')->unique()->count() >= 2;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active || ! filled($user->email)) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function ($query) use ($user) {
            $query->where('created_by', $user->id)->orWhere('assigned_to', $user->id);
            if ($user->role === 'coordinator') {
                $query->orWhereHas('reviews', fn ($reviews) => $reviews->where('reviewer_id', $user->id));
            }
            if ($user->role === 'coordinator' && User::divisionKey($user->division) !== '') {
                $query->orWhere(fn ($pending) => $pending->where('status', 'in_review')->whereNotNull('division')
                    ->whereRaw('LOWER(TRIM(division)) <> ?', [User::divisionKey($user->division)])
                    ->where('division', '<>', '')->where('created_by', '<>', $user->id)->where('assigned_to', '<>', $user->id));
            }
        });
    }

    public function scopeForTimelineRange(Builder $query, CarbonInterface $start, CarbonInterface $end, array $filters = []): Builder
    {
        return $query->whereBetween('scheduled_at', [$start, $end])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['platform'] ?? null, fn ($query, $platform) => $query->where('platform', $platform))
            ->orderBy('scheduled_at')->orderBy('id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(ContentReference::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ContentHistory::class)->latest('id');
    }
}
