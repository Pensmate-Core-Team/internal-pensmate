<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('division', 120)->nullable()->index());
        Schema::table('contents', function (Blueprint $table) {
            $table->string('division', 120)->nullable()->index();
            $table->unsignedInteger('revision_number')->default(0);
        });
        Schema::create('content_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path')->unique();
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['content_id', 'position']);
        });
        Schema::create('content_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('reviewer_role');
            $table->string('reviewer_division', 120)->nullable();
            $table->string('decision');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['content_id', 'revision_number', 'reviewer_role'], 'content_review_role_unique');
            $table->unique(['content_id', 'revision_number', 'reviewer_id'], 'content_review_person_unique');
        });
        // Keep existing media files in place and preserve the legacy primary-media fields.
        DB::table('contents')->whereNotNull('media_path')->orderBy('id')->chunkById(100, function ($contents) {
            foreach ($contents as $content) {
                DB::table('content_assets')->insertOrIgnore([
                    'content_id' => $content->id, 'storage_path' => $content->media_path,
                    'original_name' => $content->media_name ?: basename($content->media_path),
                    'mime_type' => $content->media_mime ?: 'application/octet-stream',
                    'position' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('content_assets')->exists() || DB::table('content_reviews')->exists()) {
            throw new RuntimeException('Ekspor media dan riwayat review sebelum rollback; migration tidak akan membuang data tersebut.');
        }
        Schema::dropIfExists('content_reviews');
        Schema::dropIfExists('content_assets');
        Schema::table('contents', function (Blueprint $table) {
            $table->dropIndex(['division']);
            $table->dropColumn(['division', 'revision_number']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['division']);
            $table->dropColumn('division');
        });
    }
};
