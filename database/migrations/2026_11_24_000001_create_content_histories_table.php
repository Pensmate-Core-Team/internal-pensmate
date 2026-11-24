<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('field', 32);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['content_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('content_histories')->exists()) {
            throw new RuntimeException('Ekspor riwayat perubahan konten sebelum rollback; migration tidak akan membuang riwayat.');
        }
        Schema::dropIfExists('content_histories');
    }
};
