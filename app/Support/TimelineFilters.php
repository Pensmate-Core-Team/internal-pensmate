<?php

namespace App\Support;

use App\Models\Content;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class TimelineFilters
{
    public const FLAGS = ['overdue', 'needs_assignee'];

    private const CARRIED = ['status', 'platform', 'division', 'assignee', 'flag'];

    private function __construct(private readonly array $values) {}

    public static function fromRequest(Request $request, bool $withRange, array $extraRules = []): self
    {
        $rules = [
            'status' => ['nullable', Rule::in(array_keys(Content::STATUSES))],
            'platform' => ['nullable', Rule::in(Content::PLATFORMS)],
            'division' => ['nullable', 'string', 'max:120'],
            'assignee' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'flag' => ['nullable', Rule::in(self::FLAGS)],
        ];
        if ($withRange) {
            $rules['from'] = ['nullable', 'date_format:Y-m-d'];
            $rules['to'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'];
        }

        return new self(array_filter($request->validate([...$rules, ...$extraRules]), fn ($value) => filled($value)));
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function hasRange(): bool
    {
        return isset($this->values['from']) || isset($this->values['to']);
    }

    public function carry(): array
    {
        return array_intersect_key($this->values, array_flip(self::CARRIED));
    }

    public function apply(Builder $query, array $except = []): Builder
    {
        $value = fn (string $key) => in_array($key, $except, true) ? null : $this->get($key);
        $timezone = config('app.timezone');

        return $query
            ->when($value('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($value('platform'), fn (Builder $q, $platform) => $q->where('platform', $platform))
            ->when($value('division'), fn (Builder $q, $division) => $q->whereRaw('LOWER(TRIM(division)) = ?', [User::divisionKey($division)]))
            ->when($value('assignee'), fn (Builder $q, $assignee) => $q->where('assigned_to', $assignee))
            ->when($value('from'), fn (Builder $q, $from) => $q->where('scheduled_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone)->startOfDay()))
            ->when($value('to'), fn (Builder $q, $to) => $q->where('scheduled_at', '<=', CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone)->endOfDay()))
            ->when($value('flag') === 'overdue', fn (Builder $q) => $q->overdue())
            ->when($value('flag') === 'needs_assignee', fn (Builder $q) => $q->needingAssignee());
    }

    /** @return Collection<string, string> */
    public static function divisionOptions(User $user): Collection
    {
        return Content::visibleTo($user)->whereNotNull('division')->where('division', '<>', '')
            ->distinct()->pluck('division')
            ->groupBy(fn (string $division) => User::divisionKey($division))
            ->map(fn (Collection $spellings) => $spellings->sort()->first())
            ->sort();
    }

    /** @return Collection<int, array{id: int, label: string}> */
    public static function assigneeOptions(User $user): Collection
    {
        $ids = Content::visibleTo($user)->distinct()->pluck('assigned_to');

        return User::withTrashed()->whereIn('id', $ids)->orderBy('name')->orderBy('id')->get()
            ->map(fn (User $member) => ['id' => $member->id, 'label' => $member->name.match (true) {
                ! $member->is_active || $member->trashed() => ' (nonaktif)',
                ! filled($member->email) => ' (belum aktivasi)',
                default => '',
            }])->values();
    }
}
