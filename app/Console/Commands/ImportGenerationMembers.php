<?php

namespace App\Console\Commands;

use App\Actions\Members\ImportGenerationMembers as ImportAction;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportGenerationMembers extends Command
{
    protected $signature = 'pensmate:import-members {gen1 : Path CSV Gen 1} {gen2 : Path CSV Gen 2} {--dry-run : Validasi tanpa menyimpan data}';

    protected $description = 'Import direktori Gen 1/Gen 2 tanpa membuat email atau akses login';

    public function handle(ImportAction $action): int
    {
        try {
            $result = $action->handle([1 => $this->argument('gen1'), 2 => $this->argument('gen2')], $this->option('dry-run'));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info(($this->option('dry-run') ? 'Dry run: ' : 'Import: ').$result['created'].' member baru, '.$result['skipped'].' sudah ada.');
        foreach ($result['generations'] as $generation => $count) {
            $this->line('Gen '.$generation.': '.$count);
        }

        return self::SUCCESS;
    }
}
