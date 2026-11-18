<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(array_keys(Content::STATUSES))], 'platform' => ['nullable', Rule::in(Content::PLATFORMS)]]);
        $base = Content::query()->when(! $request->user()->isAdmin(), fn ($q) => $q->where(fn ($q) => $q->where('assigned_to', $request->user()->id)->orWhere('created_by', $request->user()->id)));
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $contents = $base->with('assignee')->withCount('references')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['platform'] ?? null, fn ($q, $platform) => $q->where('platform', $platform))
            ->latest('updated_at')->paginate(10)->withQueryString();

        return view('contents.index', compact('contents', 'counts'));
    }

    public function create(Request $request)
    {
        return view('contents.form', ['content' => new Content(['platform' => 'Instagram', 'assigned_to' => $request->user()->id]), 'members' => User::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $content = new Content($data);
        $content->created_by = $request->user()->id;
        $content->status = 'draft';
        $this->attachMedia($request, $content);
        $content->save();

        return redirect()->route('contents.show', $content)->with('success', 'Draf berhasil dibuat.');
    }

    public function show(Content $content)
    {
        Gate::authorize('view', $content);

        return view('contents.show', ['content' => $content->load('assignee', 'creator', 'reviewer', 'references.creator')]);
    }

    public function edit(Content $content)
    {
        Gate::authorize('update', $content);

        return view('contents.form', ['content' => $content, 'members' => User::where('is_active', true)->orWhere('id', $content->assigned_to)->orderBy('name')->get()]);
    }

    public function update(Request $request, Content $content)
    {
        Gate::authorize('update', $content);
        $data = $this->validated($request, $content);
        DB::transaction(function () use ($request, $content, $data) {
            $locked = Content::lockForUpdate()->findOrFail($content->id);
            Gate::authorize('update', $locked);
            $locked->fill($data);
            $this->attachMedia($request, $locked);
            $locked->save();
        });

        return redirect()->route('contents.show', $content)->with('success', 'Perubahan draf tersimpan.');
    }

    public function transition(Request $request, Content $content)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['submit', 'approve', 'revise', 'publish'])], 'review_notes' => ['nullable', 'string', 'max:5000', 'required_if:action,revise'], 'published_url' => ['nullable', 'url:http,https', 'max:2000', 'required_if:action,publish']]);
        DB::transaction(function () use ($content, $data) {
            $item = Content::lockForUpdate()->findOrFail($content->id);
            switch ($data['action']) {
                case 'submit':
                    Gate::authorize('update', $item);
                    if (! filled($item->caption) || ! $item->media_path || ! $item->scheduled_at) {
                        throw ValidationException::withMessages(['action' => 'Lengkapi caption, file media, dan jadwal sebelum mengajukan review.']);
                    }
                    $item->status = 'in_review';
                    break;
                case 'approve':
                case 'revise':
                    Gate::authorize('review', $item);
                    $item->status = $data['action'] === 'approve' ? 'ready' : 'revision';
                    $item->review_notes = $data['review_notes'] ?? null;
                    $item->reviewed_by = auth()->id();
                    $item->reviewed_at = now();
                    break;
                case 'publish':
                    Gate::authorize('publish', $item);
                    $item->status = 'published';
                    $item->published_url = $data['published_url'];
                    $item->published_at = now();
                    break;
            }
            $item->save();
        });

        return back()->with('success', 'Status konten berhasil diperbarui.');
    }

    public function media(Content $content)
    {
        Gate::authorize('view', $content);
        abort_unless($content->media_path && Storage::disk('local')->exists($content->media_path), 404);

        return response()->file(Storage::disk('local')->path($content->media_path), ['Content-Type' => $content->media_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function validated(Request $request, ?Content $content = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'caption' => ['nullable', 'string', 'max:15000'],
            'platform' => ['required', Rule::in(Content::PLATFORMS)], 'scheduled_at' => ['nullable', 'date'],
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where(fn ($q) => $q->where('is_active', true)->when($content, fn ($q) => $q->orWhere('id', $content->assigned_to))))],
            'media' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm', 'max:51200'],
        ]);
        if (! $request->user()->isAdmin()) {
            $data['assigned_to'] = $content?->assigned_to ?? $request->user()->id;
        }
        unset($data['media']);

        return $data;
    }

    private function attachMedia(Request $request, Content $content): void
    {
        if ($request->hasFile('media')) {
            $old = $content->media_path;
            $file = $request->file('media');
            $content->media_path = $file->store('content-media', 'local');
            $content->media_name = $file->getClientOriginalName();
            $content->media_mime = $file->getMimeType();
            if ($old && $old !== $content->media_path) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($old));
            }
        }
    }
}
