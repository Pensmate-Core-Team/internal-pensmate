<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Support\TimelineFilters;
use Illuminate\Http\Request;

class TimelineBoardController extends Controller
{
    public const COLUMN_LIMIT = 50;

    public const RECENT_DAYS = 30;

    public function __invoke(Request $request)
    {
        $filters = TimelineFilters::fromRequest($request, true);
        $user = $request->user();
        $columns = [];
        foreach (Content::STATUSES as $status => $label) {
            $limitedToRecent = $status === 'published' && ! $filters->hasRange();
            $query = fn () => $filters->apply(Content::visibleTo($user), ['status'])->where('status', $status)
                ->when($limitedToRecent, fn ($q) => $q->where('published_at', '>=', now()->subDays(self::RECENT_DAYS)));
            $total = $query()->count();
            $columns[] = ['status' => $status, 'label' => $label, 'total' => $total,
                'items' => $query()->timelineOrder()->with('assignee')->withCount('references')
                    ->take(self::COLUMN_LIMIT)->get(),
                'more' => $total > self::COLUMN_LIMIT, 'limitedToRecent' => $limitedToRecent];
        }

        return response()->view('timeline.board', [
            'columns' => $columns, 'filters' => $filters,
            'divisions' => TimelineFilters::divisionOptions($user),
            'assignees' => TimelineFilters::assigneeOptions($user),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }
}
