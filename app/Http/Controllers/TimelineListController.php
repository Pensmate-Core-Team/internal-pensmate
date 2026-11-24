<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Support\TimelineFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TimelineListController extends Controller
{
    public const SORTS = ['scheduled' => 'Jadwal terdekat', '-scheduled' => 'Jadwal terjauh', 'status' => 'Status'];

    public function __invoke(Request $request)
    {
        $filters = TimelineFilters::fromRequest($request, true, ['sort' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))]]);
        $sort = $filters->get('sort') ?? 'scheduled';
        $user = $request->user();
        $base = fn () => $filters->apply(Content::visibleTo($user), ['flag']);
        $contents = self::sorted($filters->apply(Content::visibleTo($user)), $sort)
            ->with('assignee')->withCount('references')->paginate(25)->withQueryString();

        return response()->view('timeline.list', [
            'contents' => $contents, 'filters' => $filters, 'sort' => $sort,
            'summary' => ['total' => $base()->count(), 'overdue' => $base()->overdue()->count(),
                'needs_assignee' => $base()->needingAssignee()->count()],
            'hasAnyContent' => Content::visibleTo($user)->exists(),
            'divisions' => TimelineFilters::divisionOptions($user),
            'assignees' => TimelineFilters::assigneeOptions($user),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    public static function sorted(Builder $query, string $sort): Builder
    {
        if ($sort === 'status') {
            $statuses = array_keys(Content::STATUSES);
            $cases = implode(' ', array_map(fn ($i) => "WHEN ? THEN {$i}", array_keys($statuses)));
            $query->orderByRaw("CASE status {$cases} ELSE ".count($statuses).' END', $statuses);
        }

        return $query->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at', $sort === '-scheduled' ? 'desc' : 'asc')->orderBy('id');
    }
}
