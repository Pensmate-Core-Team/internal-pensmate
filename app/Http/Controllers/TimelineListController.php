<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Support\TimelineFilters;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimelineListController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = TimelineFilters::fromRequest($request, true, ['sort' => ['nullable', Rule::in(array_keys(Content::TIMELINE_SORTS))]]);
        $sort = $filters->get('sort') ?? 'scheduled';
        $user = $request->user();
        $base = fn () => $filters->apply(Content::visibleTo($user), ['flag']);
        $contents = $filters->apply(Content::visibleTo($user))->timelineOrder($sort)
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
}
