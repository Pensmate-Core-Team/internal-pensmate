<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function describe(): string
    {
        return match ($this->field) {
            'status' => $this->old_value === null
                ? 'membuat konten dengan status '.$this->statusLabel($this->new_value)
                : 'mengubah status '.$this->statusLabel($this->old_value).' → '.$this->statusLabel($this->new_value),
            'scheduled_at' => 'mengubah jadwal '.$this->scheduleLabel($this->old_value).' → '.$this->scheduleLabel($this->new_value),
            'assigned_to' => 'mengganti PIC '.$this->memberLabel($this->old_value).' → '.$this->memberLabel($this->new_value),
            default => 'mengubah '.$this->field,
        };
    }

    private function statusLabel(?string $status): string
    {
        return Content::STATUSES[$status] ?? (string) $status;
    }

    private function scheduleLabel(?string $value): string
    {
        return $value === null ? 'Belum dijadwalkan'
            : CarbonImmutable::parse($value)->timezone(config('app.timezone'))->locale('id')->translatedFormat('d M Y H:i').' WIB';
    }

    private function memberLabel(?string $id): string
    {
        return ($id === null ? null : User::withTrashed()->find($id)?->name) ?? 'Anggota tidak ditemukan';
    }
}
