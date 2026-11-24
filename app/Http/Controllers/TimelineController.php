<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Carbon\CarbonImmutable;
use App\Support\TimelineFilters;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $filters = TimelineFilters::fromRequest($request, false);
        $today = CarbonImmutable::now(config('app.timezone'));
        $month = $request->input('month') ?? $today->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'))->startOfMonth();
        $end = $start->endOfMonth();
        $contents = $filters->apply(Content::visibleTo($request->user())->forTimelineRange($start, $end))
            ->with('assignee', 'references')->get();
        $byDate = $contents->groupBy(fn (Content $content) => $content->scheduled_at->format('Y-m-d'));
        $days = [];
        for ($date = $start->startOfWeek(1); $date->lte($end->endOfWeek(0)); $date = $date->addDay()) {
            $days[] = ['date' => $date, 'inMonth' => $date->format('Y-m') === $month,
                'isToday' => $date->isSameDay($today), 'contents' => $byDate->get($date->format('Y-m-d'), collect())];
        }
        $url = fn (string $value) => route('timeline.index', ['month' => $value, ...$filters->carry()]);

        return response()->view('timeline.index', [
            'month' => $month, 'monthLabel' => $start->locale('id')->translatedFormat('F Y'),
            'contents' => $contents, 'days' => $days, 'filters' => $filters,
            'divisions' => TimelineFilters::divisionOptions($request->user()),
            'assignees' => TimelineFilters::assigneeOptions($request->user()),
            'previousUrl' => $url($start->subMonth()->format('Y-m')),
            'nextUrl' => $url($start->addMonth()->format('Y-m')),
            'todayUrl' => $url($today->format('Y-m')),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }
}
