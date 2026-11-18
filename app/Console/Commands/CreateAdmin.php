<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'pensmate:admin';

    protected $description = 'Buat akun admin Pensmate melalui prompt interaktif';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Nama admin'), 'email' => $this->ask('Email admin'), 'password' => $this->secret('Password (minimal 10 karakter, huruf dan angka)')];
        $validator = Validator::make($data, ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users'], 'password' => ['required', Password::min(10)->letters()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create([...$data, 'role' => 'admin', 'is_active' => true]);
        $this->info('Admin berhasil dibuat.');

        return self::SUCCESS;
    }
}
