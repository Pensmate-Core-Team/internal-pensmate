<?php

namespace App\Http\Controllers;

use App\Actions\Content\SaveContent;
use App\Actions\Content\TransitionContent;
use App\Models\Content;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(array_keys(Content::STATUSES))], 'platform' => ['nullable', Rule::in(Content::PLATFORMS)]]);
        $base = Content::visibleTo($request->user());
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $contents = $base->with('assignee')->withCount('references', 'assets')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['platform'] ?? null, fn ($q, $platform) => $q->where('platform', $platform))
            ->latest('updated_at')->paginate(10)->withQueryString();

        return view('contents.index', compact('contents', 'counts'));
    }

    public function create(Request $request)
    {
        return view('contents.form', ['content' => new Content(['platform' => 'Instagram',
            'assigned_to' => $request->user()->id, 'division' => $request->user()->division]),
            'members' => User::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, SaveContent $action)
    {
        $content = $action->handle($request->user(), null, $this->validated($request), $this->files($request));

        return redirect()->route('contents.show', $content)->with('success', 'Draf berhasil dibuat.');
    }

    public function show(Content $content)
    {
        Gate::authorize('view', $content);

        return view('contents.show', ['content' => $content->load('assignee', 'creator', 'reviewer', 'assets', 'reviews.reviewer', 'references.creator')]);
    }

    public function edit(Content $content)
    {
        Gate::authorize('update', $content);

        return view('contents.form', ['content' => $content->load('assets'), 'members' => User::where('is_active', true)->orWhere('id', $content->assigned_to)->orderBy('name')->get()]);
    }

    public function update(Request $request, Content $content, SaveContent $action)
    {
        Gate::authorize('update', $content);
        $action->handle($request->user(), $content, $this->validated($request, $content), $this->files($request),
            $request->input('remove_assets', []), $request->hasFile('media'), $request->input('asset_order', []));

        return redirect()->route('contents.show', $content)->with('success', 'Perubahan draf tersimpan.');
    }

    public function transition(Request $request, Content $content, TransitionContent $action)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['submit', 'approve', 'revise', 'publish'])],
            'review_notes' => ['nullable', 'string', 'max:5000', 'required_if:action,revise'],
            'published_url' => ['nullable', 'url:http,https', 'max:2000', 'required_if:action,publish']]);
        $action->handle($request->user(), $content, $data);

        return back()->with('success', match ($data['action']) {
            'submit' => 'Draf diajukan untuk persetujuan admin dan koordinator divisi lain.',
            'revise' => 'Permintaan revisi tersimpan. Pembuat dapat memperbaiki draf.',
            'publish' => 'Konten ditandai terunggah.',
            default => 'Persetujuan tersimpan. Konten siap upload setelah kedua pihak menyetujui.',
        });
    }

    public function media(Content $content)
    {
        Gate::authorize('view', $content);
        abort_unless($content->media_path && Storage::disk('local')->exists($content->media_path), 404);

        return response()->file(Storage::disk('local')->path($content->media_path), ['Content-Type' => $content->media_mime,
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function download(Content $content)
    {
        Gate::authorize('download', $content);
        abort_unless($content->media_path && Storage::disk('local')->exists($content->media_path), 404);

        return response()->download(Storage::disk('local')->path($content->media_path), $content->media_name,
            ['Content-Type' => $content->media_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function files(Request $request): array
    {
        return [...($request->hasFile('media') ? [$request->file('media')] : []), ...$request->file('media_files', [])];
    }

    private function validated(Request $request, ?Content $content = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'caption' => ['nullable', 'string', 'max:15000'],
            'platform' => ['required', Rule::in(Content::PLATFORMS)], 'scheduled_at' => ['nullable', 'date'],
            'division' => ['nullable', 'string', 'max:120'],
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where(fn ($q) => $q->where('is_active', true)->when($content, fn ($q) => $q->orWhere('id', $content->assigned_to))))],
            'media' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm', 'max:51200'],
            'media_files' => ['nullable', 'array', 'max:10'],
            'media_files.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm', 'max:51200'],
            'asset_order' => ['sometimes', 'array', 'max:10'], 'asset_order.*' => ['integer', 'distinct'],
            'remove_assets' => ['sometimes', 'array', 'max:10'], 'remove_assets.*' => ['integer', 'distinct'],
        ]);
        if (! $request->user()->isAdmin()) {
            $data['assigned_to'] = $content?->assigned_to ?? $request->user()->id;
            $data['division'] = $content?->division ?? $request->user()->division;
        } else {
            $data['division'] = isset($data['division']) ? Str::squish($data['division']) : $content?->division;
        }
        unset($data['media'], $data['media_files'], $data['remove_assets'], $data['asset_order']);

        return $data;
    }
}
