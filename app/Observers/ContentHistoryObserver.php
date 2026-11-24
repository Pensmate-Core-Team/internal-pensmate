<?php

namespace App\Observers;

use App\Models\Content;
use App\Models\ContentHistory;
use Carbon\CarbonInterface;

class ContentHistoryObserver
{
    public const FIELDS = ['status', 'scheduled_at', 'assigned_to'];

    public function created(Content $content): void
    {
        $this->record($content, 'status', null, $content->status);
    }

    public function updated(Content $content): void
    {
        foreach (self::FIELDS as $field) {
            if ($content->wasChanged($field)) {
                $this->record($content, $field, $content->getOriginal($field), $content->getAttribute($field));
            }
        }
    }

    private function record(Content $content, string $field, mixed $old, mixed $new): void
    {
        ContentHistory::forceCreate([
            'content_id' => $content->id,
            'actor_id' => auth()->id(),
            'field' => $field,
            'old_value' => $this->serialize($old),
            'new_value' => $this->serialize($new),
            'note' => $field === 'status' && $new === 'revision' ? $content->review_notes : null,
            'created_at' => now(),
        ]);
    }

    private function serialize(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy()->timezone(config('app.timezone'))->toIso8601String();
        }

        return $value === null ? null : (string) $value;
    }
}
