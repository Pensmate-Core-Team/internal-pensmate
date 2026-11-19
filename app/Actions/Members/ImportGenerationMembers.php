<?php

namespace App\Actions\Members;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportGenerationMembers
{
    public function handle(array $sources, bool $dryRun = false): array
    {
        $rows = [];
        foreach ($sources as $generation => $path) {
            $rows = [...$rows, ...$this->read($path, (int) $generation)];
        }

        return DB::transaction(function () use ($rows, $dryRun) {
            $result = ['created' => 0, 'skipped' => 0, 'generations' => []];
            foreach ($rows as $row) {
                // Archived rows also count as imported; retries never resurrect them.
                if (User::withTrashed()->where('import_source_key', $row['import_source_key'])->exists()) {
                    $result['skipped']++;

                    continue;
                }
                if (! $dryRun) {
                    $user = new User($row);
                    $user->forceFill(['import_source_key' => $row['import_source_key'], 'role' => 'member',
                        'email' => null, 'password' => Str::random(64), 'is_active' => true]);
                    $user->save();
                }
                $result['created']++;
                $result['generations'][$row['generation']] = ($result['generations'][$row['generation']] ?? 0) + 1;
            }

            return $result;
        });
    }

    private function read(string $path, int $generation): array
    {
        if (! in_array($generation, [1, 2], true) || ! is_readable($path)) {
            throw ValidationException::withMessages(['csv' => 'File CSV Gen 1/Gen 2 tidak dapat dibaca.']);
        }
        $handle = fopen($path, 'r');
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header) {
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            }
            if ($header !== ['No', 'Nama', $generation === 1 ? 'Kelas' : 'Program Studi']) {
                throw ValidationException::withMessages(['csv' => 'Header CSV tidak sesuai format generasi.']);
            }
            $rows = [];
            $numbers = [];
            while (($columns = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count(array_filter($columns, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                $columns = array_map(fn ($value) => trim((string) $value), $columns);
                if (count($columns) !== 3 || ! ctype_digit($columns[0]) || (int) $columns[0] < 1
                    || isset($numbers[(int) $columns[0]]) || $columns[1] === '' || mb_strlen($columns[1]) > 120
                    || $columns[2] === '' || mb_strlen($columns[2]) > 255) {
                    throw ValidationException::withMessages(['csv' => 'Baris CSV tidak valid atau nomor berulang. Tidak ada data yang diimpor.']);
                }
                $numbers[(int) $columns[0]] = true;
                $rows[] = ['name' => $columns[1], 'generation' => $generation,
                    'class_name' => $generation === 1 ? $columns[2] : null,
                    'study_program' => $generation === 2 ? $columns[2] : null,
                    'import_source_key' => 'pensmate-gen-'.$generation.':'.(int) $columns[0]];
            }
            if ($rows === []) {
                throw ValidationException::withMessages(['csv' => 'CSV tidak memuat data member.']);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
