<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->unsignedTinyInteger('generation')->nullable()->index();
            $table->string('class_name')->nullable();
            $table->string('study_program')->nullable();
            $table->string('import_source_key', 60)->nullable()->unique();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Isi email member sebelum rollback. Data member tidak boleh dibuang untuk memenuhi schema lama.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->dropIndex(['generation']);
            $table->dropUnique(['import_source_key']);
            $table->dropColumn(['generation', 'class_name', 'study_program', 'import_source_key', 'deleted_at']);
        });
    }
};
