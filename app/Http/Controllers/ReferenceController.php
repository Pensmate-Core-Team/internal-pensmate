<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\ContentReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReferenceController extends Controller
{
    public function store(Request $request, Content $content)
    {
        DB::transaction(function () use ($request, $content) {
            $locked = Content::lockForUpdate()->findOrFail($content->id);
            Gate::authorize('update', $locked);
            $locked->references()->create([...$this->validated($request, 'newReference'), 'created_by' => $request->user()->id]);
        });

        return back()->with('success', 'Referensi berhasil ditambahkan.');
    }

    public function update(Request $request, Content $content, ContentReference $reference)
    {
        DB::transaction(function () use ($request, $content, $reference) {
            $locked = Content::lockForUpdate()->findOrFail($content->id);
            $this->authorizeReference($locked, $reference);
            $reference->update($this->validated($request, 'reference-'.$reference->id));
        });

        return back()->with('success', 'Referensi berhasil diperbarui.');
    }

    public function destroy(Content $content, ContentReference $reference)
    {
        DB::transaction(function () use ($content, $reference) {
            $locked = Content::lockForUpdate()->findOrFail($content->id);
            $this->authorizeReference($locked, $reference);
            $reference->delete();
        });

        return back()->with('success', 'Referensi berhasil dihapus.');
    }

    private function authorizeReference(Content $content, ContentReference $reference): void
    {
        abort_unless($reference->content_id === $content->id, 404);
        Gate::authorize('update', $content);
        abort_unless(auth()->user()->isAdmin() || $reference->created_by === auth()->id(), 403);
    }

    private function validated(Request $request, string $bag): array
    {
        return $request->validateWithBag($bag, ['title' => ['required', 'string', 'max:180'], 'url' => ['required', 'url:http,https', 'max:2000'], 'notes' => ['nullable', 'string', 'max:3000']]);
    }
}
