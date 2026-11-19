<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAsset extends Model
{
    protected $fillable = ['storage_path', 'original_name', 'mime_type', 'file_size', 'position'];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
