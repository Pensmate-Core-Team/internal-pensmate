<?php

namespace App\Actions\Content;

use App\Models\Content;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SaveContent
{
    public function handle(User $actor, ?Content $existing, array $data, array $files, array $removeIds = [], bool $replacePrimary = false, array $order = []): Content
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($actor, $existing, $data, $files, $removeIds, $replacePrimary, $order, &$stored) {
                $content = $existing ? Content::lockForUpdate()->findOrFail($existing->id) : new Content;
                $actor = User::lockForUpdate()->findOrFail($actor->id);
                abort_unless($actor->is_active && filled($actor->email), 403);
                if (! $actor->isAdmin()) {
                    $data['assigned_to'] = $existing ? $content->assigned_to : $actor->id;
                    $data['division'] = $existing ? $content->division : $actor->division;
                }
                if ($existing) {
                    Gate::forUser($actor)->authorize('update', $content);
                } else {
                    $content->created_by = $actor->id;
                    $content->status = 'draft';
                }
                $content->fill($data)->save();
                // Handle legacy primary media written by existing integrations too.
                if ($content->media_path && ! $content->assets()->exists()) {
                    $content->assets()->create(['storage_path' => $content->media_path,
                        'original_name' => $content->media_name ?: basename($content->media_path),
                        'mime_type' => $content->media_mime ?: 'application/octet-stream', 'position' => 0]);
                }
                $assets = $content->assets()->get();
                $removeIds = array_map('intval', $removeIds);
                if (array_diff($removeIds, $assets->modelKeys()) !== []) {
                    throw ValidationException::withMessages(['remove_assets' => 'Media yang dipilih bukan milik draf ini.']);
                }
                if (array_diff(array_map('intval', $order), $assets->modelKeys()) !== []) {
                    throw ValidationException::withMessages(['asset_order' => 'Urutan berisi media yang bukan milik draf ini.']);
                }
                if ($replacePrimary && $assets->isNotEmpty()) {
                    $removeIds[] = $assets->first()->id;
                }
                $removed = $assets->whereIn('id', $removeIds);
                if ($assets->count() - $removed->count() + count($files) > 10) {
                    throw ValidationException::withMessages(['media_files' => 'Maksimal 10 file media per konten.']);
                }
                foreach ($removed as $asset) {
                    $asset->delete();
                }
                foreach ($files as $file) {
                    $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                    $name = str_replace(["\r", "\n", "\0"], '', $name);
                    if ($name === '' || mb_strlen($name) > 255) {
                        throw ValidationException::withMessages(['media_files' => 'Nama file wajib terisi dan maksimal 255 karakter.']);
                    }
                    $path = $file->store('content-media', 'local');
                    if (! $path) {
                        throw new RuntimeException('Media gagal disimpan. Coba kembali.');
                    }
                    $stored[] = $path;
                    $content->assets()->create(['storage_path' => $path, 'original_name' => $name,
                        'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(),
                        'position' => $content->assets()->max('position') + 1]);
                }
                $remaining = $content->assets()->get();
                $byId = $remaining->keyBy('id');
                $remaining = collect($order)->filter(fn ($id) => $byId->has($id))->map(fn ($id) => $byId->get($id))
                    ->concat($remaining->whereNotIn('id', $order))->values();
                foreach ($remaining as $position => $asset) {
                    $asset->update(['position' => $position]);
                }
                $first = $remaining->first();
                $content->forceFill(['media_path' => $first?->storage_path, 'media_name' => $first?->original_name,
                    'media_mime' => $first?->mime_type])->save();
                $paths = $removed->pluck('storage_path')->all();
                if ($paths !== []) {
                    DB::afterCommit(function () use ($paths) {
                        try {
                            Storage::disk('local')->delete($paths);
                        } catch (Throwable $exception) {
                            report($exception);
                        }
                    });
                }

                return $content;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }
    }
}
