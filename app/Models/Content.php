<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Content extends Model
{
    public const STATUSES = ['draft' => 'Draf', 'in_review' => 'Dalam review', 'revision' => 'Perlu revisi', 'ready' => 'Siap upload', 'published' => 'Terunggah'];

    public const PLATFORMS = ['Instagram', 'TikTok', 'YouTube', 'LinkedIn', 'X'];

    protected $fillable = ['title', 'caption', 'platform', 'assigned_to', 'scheduled_at'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'reviewed_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function references(): HasMany
    {
        return $this->hasMany(ContentReference::class);
    }
}
