<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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

    /**
     * Names of every member referenced by PIC-change rows, loaded in one query
     * (archived members included). Pass the result to describe().
     *
     * @param  iterable<self>  $histories
     * @return Collection<int, string>
     */
    public static function memberNames(iterable $histories): Collection
    {
        $ids = collect($histories)->where('field', 'assigned_to')
            ->flatMap(fn (self $history) => [$history->old_value, $history->new_value])->filter()->unique()->values();

        return $ids->isEmpty() ? collect() : User::withTrashed()->whereIn('id', $ids)->pluck('name', 'id');
    }

    /** @param  Collection<int, string>|null  $names  from memberNames(); looked up per row when omitted */
    public function describe(?Collection $names = null): string
    {
        $names ??= self::memberNames([$this]);

        return match ($this->field) {
            'status' => $this->old_value === null
                ? 'membuat konten dengan status '.$this->statusLabel($this->new_value)
                : 'mengubah status '.$this->statusLabel($this->old_value).' → '.$this->statusLabel($this->new_value),
            'scheduled_at' => 'mengubah jadwal '.$this->scheduleLabel($this->old_value).' → '.$this->scheduleLabel($this->new_value),
            'assigned_to' => 'mengganti PIC '.$this->memberLabel($names, $this->old_value).' → '.$this->memberLabel($names, $this->new_value),
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

    private function memberLabel(Collection $names, ?string $id): string
    {
        return ($id === null ? null : $names->get((int) $id)) ?? 'Anggota tidak ditemukan';
    }
}
