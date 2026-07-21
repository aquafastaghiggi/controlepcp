<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupBancoCommand extends Command
{
    protected $signature = 'backup:banco {--dias-retencao=14 : Quantos dias de backup manter}';

    protected $description = 'Gera um dump compactado do banco de dados e remove backups mais antigos que a retenção configurada';

    public function handle(): int
    {
        $destino = storage_path('app/backups');
        File::ensureDirectoryExists($destino);

        $timestamp = now()->format('Y-m-d_His');
        $banco     = (string) config('database.connections.mysql.database');
        $arquivo   = "{$destino}/{$banco}_{$timestamp}.sql.gz";

        $host = (string) config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port');
        $user = (string) config('database.connections.mysql.username');
        $pass = (string) config('database.connections.mysql.password');

        $this->info("Gerando backup de \"{$banco}\"...");

        $comando = sprintf(
            'mysqldump --default-character-set=utf8mb4 --single-transaction -h%s -P%s -u%s %s | gzip > %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($banco),
            escapeshellarg($arquivo)
        );

        $processo = Process::fromShellCommandline($comando);
        $processo->setEnv(['MYSQL_PWD' => $pass]);
        $processo->setTimeout(900);
        $processo->run();

        if (! $processo->isSuccessful() || ! File::exists($arquivo) || File::size($arquivo) === 0) {
            $this->error('Falha ao gerar backup: ' . $processo->getErrorOutput());
            if (File::exists($arquivo)) {
                File::delete($arquivo);
            }
            return self::FAILURE;
        }

        $tamanhoMb = round(File::size($arquivo) / 1024 / 1024, 1);
        $this->info("Backup gerado: {$arquivo} ({$tamanhoMb} MB)");

        $this->removerBackupsAntigos($destino, (int) $this->option('dias-retencao'));

        return self::SUCCESS;
    }

    private function removerBackupsAntigos(string $destino, int $diasRetencao): void
    {
        $limite    = Carbon::now()->subDays($diasRetencao);
        $removidos = 0;

        foreach (File::files($destino) as $arquivo) {
            if (! str_ends_with($arquivo->getFilename(), '.sql.gz')) {
                continue;
            }

            if (Carbon::createFromTimestamp($arquivo->getMTime())->lt($limite)) {
                File::delete($arquivo->getPathname());
                $removidos++;
            }
        }

        if ($removidos > 0) {
            $this->info("{$removidos} backup(s) antigo(s) removido(s) (retenção: {$diasRetencao} dias).");
        }
    }
}
