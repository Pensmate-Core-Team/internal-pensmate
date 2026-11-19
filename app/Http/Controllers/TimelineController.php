<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimelineController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(array_keys(Content::STATUSES))],
            'platform' => ['nullable', Rule::in(Content::PLATFORMS)],
        ]);
        $today = CarbonImmutable::now(config('app.timezone'));
        $month = $filters['month'] ?? $today->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'))->startOfMonth();
        $end = $start->endOfMonth();
        $contents = Content::visibleTo($request->user())->forTimelineRange($start, $end, $filters)
            ->with('assignee', 'references')->get();
        $byDate = $contents->groupBy(fn (Content $content) => $content->scheduled_at->format('Y-m-d'));
        $days = [];
        for ($date = $start->startOfWeek(1); $date->lte($end->endOfWeek(0)); $date = $date->addDay()) {
            $days[] = ['date' => $date, 'inMonth' => $date->format('Y-m') === $month,
                'isToday' => $date->isSameDay($today), 'contents' => $byDate->get($date->format('Y-m-d'), collect())];
        }
        $url = fn (string $value) => route('timeline.index', array_filter([
            'month' => $value, 'status' => $filters['status'] ?? null, 'platform' => $filters['platform'] ?? null,
        ], fn ($value) => filled($value)));

        return response()->view('timeline.index', [
            'month' => $month, 'monthLabel' => $start->locale('id')->translatedFormat('F Y'),
            'contents' => $contents, 'days' => $days,
            'previousUrl' => $url($start->subMonth()->format('Y-m')),
            'nextUrl' => $url($start->addMonth()->format('Y-m')),
            'todayUrl' => $url($today->format('Y-m')),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }
}
