<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database
                            {--path= : Directorio de destino (por defecto storage/app/backups)}
                            {--keep=7 : Número de backups a conservar}';

    protected $description = 'Crea un backup de la base de datos MySQL (mysqldump) con nombre de fecha';

    public function handle()
    {
        $config = config('database.connections.mysql');

        $dir = $this->option('path') ?: storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'backup_'.date('Y-m-d_H-i-s').'.sql';
        $fullPath = rtrim($dir, '/').'/'.$filename;

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? '3306'),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg($fullPath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::error('Backup de BD falló', ['output' => implode("\n", $output)]);
            $this->error('El backup de la base de datos falló.');
            if (! empty($output)) {
                $this->line(implode("\n", $output));
            }
            return 1;
        }

        $this->trimOldBackups($dir, (int) $this->option('keep'));
        $this->info("Backup creado: {$fullPath}");
        Log::info('Backup de BD completado', ['file' => $fullPath]);
        return 0;
    }

    protected function trimOldBackups($dir, $keep)
    {
        $files = glob(rtrim($dir, '/').'/backup_*.sql');
        if (! $files) {
            return;
        }
        // ordenar por nombre (que incluye fecha) => más recientes al final
        sort($files);
        $toDelete = count($files) - $keep;
        foreach (array_slice($files, 0, max(0, $toDelete)) as $old) {
            @unlink($old);
            $this->line("Backup antiguo eliminado: ".basename($old));
        }
    }
}
