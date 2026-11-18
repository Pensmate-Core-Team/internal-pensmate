<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WorkspaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Demo seeder hanya boleh dijalankan di lingkungan local.');
        }
        $password = env('DEMO_PASSWORD');
        if (! $password || strlen($password) < 10) {
            throw new \RuntimeException('Set DEMO_PASSWORD minimal 10 karakter sebelum menjalankan seeder.');
        }
        $admin = User::firstOrCreate(['email' => 'admin@pensmate.test'], ['name' => 'Hani', 'password' => Hash::make($password), 'role' => 'admin', 'is_active' => true]);
        $sheva = User::firstOrCreate(['email' => 'sheva@pensmate.test'], ['name' => 'Sheva', 'password' => Hash::make($password), 'role' => 'member', 'is_active' => true]);
        $galang = User::firstOrCreate(['email' => 'galang@pensmate.test'], ['name' => 'Galang', 'password' => Hash::make($password), 'role' => 'member', 'is_active' => true]);
        foreach ([['Kenalan dengan tim Pensmate', 'Instagram', 'draft', $admin], ['Cerita di balik produksi konten', 'TikTok', 'draft', $sheva], ['Catatan kegiatan minggu ini', 'Instagram', 'revision', $galang]] as [$title, $platform, $status, $owner]) {
            $content = Content::where('title', $title)->where('created_by', $admin->id)->first();
            if (! $content) {
                $content = new Content(['title' => $title, 'caption' => 'Data demo lokal. Ganti caption dan lengkapi media sebelum mengajukan review.', 'platform' => $platform, 'assigned_to' => $owner->id, 'scheduled_at' => now()->addDays(2)]);
                $content->created_by = $admin->id;
                $content->status = $status;
                $content->review_notes = $status === 'revision' ? 'Lengkapi foto kegiatan dan cek kembali caption sebelum diajukan.' : null;
                $content->save();
                $content->references()->create(['title' => 'Panduan konten Instagram', 'url' => 'https://creators.instagram.com/', 'notes' => 'Referensi demo untuk format dan praktik pembuatan konten.', 'created_by' => $admin->id]);
            }
        }
    }
}
