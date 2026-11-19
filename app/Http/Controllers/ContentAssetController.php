<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\ContentAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ContentAssetController extends Controller
{
    public function show(Content $content, ContentAsset $asset)
    {
        $this->authorizeAsset($content, $asset, 'view');

        return response()->file(Storage::disk('local')->path($asset->storage_path), [
            'Content-Type' => $asset->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Content $content, ContentAsset $asset)
    {
        $this->authorizeAsset($content, $asset, 'download');

        return response()->download(Storage::disk('local')->path($asset->storage_path), $asset->original_name, [
            'Content-Type' => $asset->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeAsset(Content $content, ContentAsset $asset, string $ability): void
    {
        abort_unless($asset->content_id === $content->id, 404);
        Gate::authorize($ability, $content);
        abort_unless(Storage::disk('local')->exists($asset->storage_path), 404);
    }
}
