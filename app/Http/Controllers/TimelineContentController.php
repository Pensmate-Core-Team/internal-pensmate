<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TimelineContentController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = $request->validate(['start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'status' => ['nullable', Rule::in(array_keys(Content::STATUSES))],
            'platform' => ['nullable', Rule::in(Content::PLATFORMS)], 'page' => ['sometimes', 'integer', 'min:1']]);
        $start = CarbonImmutable::parse($filters['start'], config('app.timezone'))->startOfDay();
        $end = CarbonImmutable::parse($filters['end'], config('app.timezone'))->endOfDay();
        if ($start->startOfDay()->diffInDays($end->startOfDay()) > 92) {
            throw ValidationException::withMessages(['end' => 'Rentang timeline maksimal 93 hari.']);
        }
        $contents = Content::visibleTo($request->user())->forTimelineRange($start, $end, $filters)
            ->with('assignee', 'assets', 'references')->paginate(100)->withQueryString();
        $data = $contents->map(function (Content $content) use ($request) {
            $canDownload = Gate::forUser($request->user())->allows('download', $content);

            return ['id' => $content->id, 'title' => $content->title, 'start' => $content->scheduled_at->toIso8601String(),
                'platform' => $content->platform, 'status' => $content->status, 'status_label' => Content::STATUSES[$content->status],
                'revision_number' => $content->revision_number, 'division' => $content->division,
                'assignee' => ['id' => $content->assignee->id, 'name' => $content->assignee->name],
                'detail_url' => route('contents.show', $content), 'references_url' => route('contents.show', $content).'#references',
                'references' => $content->references->map(fn ($reference) => $reference->only('id', 'title', 'url', 'notes'))->values(),
                'assets' => $content->assets->map(fn ($asset) => [
                    'id' => $asset->id, 'name' => $asset->original_name, 'mime_type' => $asset->mime_type, 'position' => $asset->position,
                    'preview_url' => route('contents.assets.show', [$content, $asset]),
                    'download_url' => $canDownload ? route('contents.assets.download', [$content, $asset]) : null,
                ])->values()];
        });

        return response()->json(['data' => $data->values(), 'pagination' => ['current_page' => $contents->currentPage(),
            'last_page' => $contents->lastPage(), 'total' => $contents->total(), 'next_page_url' => $contents->nextPageUrl()]])
            ->header('Cache-Control', 'private, no-store');
    }
}
