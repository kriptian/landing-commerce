<?php

namespace App\Jobs;

use App\Models\DeploymentRun;
use App\Services\DeploymentAuthorizer;
use App\Services\DeploymentProcess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class PrepareDeployment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 600;

    public int $timeout = 7200;

    public bool $failOnTimeout = true;

    public function __construct(public int $deploymentRunId)
    {
        $this->onConnection('database')->onQueue('deployment-preparation');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('production-deployment'))->releaseAfter(10)->expireAfter(7300)->shared()];
    }

    public function handle(DeploymentProcess $process, DeploymentAuthorizer $authorizer): void
    {
        $run = DeploymentRun::findOrFail($this->deploymentRunId);

        if ($run->status !== 'preparing') {
            return;
        }

        $run->update(['started_at' => now(), 'error' => null]);

        try {
            if (! $authorizer->allows($run->user) || (int) $run->store_id !== (int) $run->user->store_id) {
                throw new RuntimeException('La autorización de despliegue ya no es válida.');
            }

            $originResult = Process::path(base_path())->timeout(60)->run(['git', 'remote', 'get-url', 'origin']);

            if ($originResult->failed()) {
                throw new RuntimeException('No se pudo verificar el remoto origin.');
            }

            $origin = trim($originResult->output());

            if (! hash_equals(config('deployment.repository_https'), $origin)) {
                throw new RuntimeException('El remoto origin no corresponde al repositorio autorizado.');
            }

            $process->run($run, ['git', 'fetch', 'origin', config('deployment.branch')], 'Actualizando origin/main', 300);
            $branch = trim($process->run($run, ['git', 'branch', '--show-current'], 'Verificando rama', 60)->output());
            $headSha = trim($process->run($run, ['git', 'rev-parse', 'HEAD'], 'Verificando commit local', 60)->output());
            $remoteSha = trim($process->run(
                $run,
                ['git', 'rev-parse', 'refs/remotes/origin/'.config('deployment.branch')],
                'Verificando commit remoto',
                60,
            )->output());

            if ($branch !== config('deployment.branch')) {
                throw new RuntimeException('La preparación solo puede ejecutarse desde la rama main.');
            }

            if (! hash_equals($remoteSha, $headSha)) {
                throw new RuntimeException('La rama main local debe estar sincronizada con origin/main antes de preparar.');
            }

            $process->run($run, ['php', 'ops/backup-local-database.php'], 'Respaldando la base local', 900);
            $process->run($run, ['composer', 'install', '--no-interaction', '--prefer-dist'], 'Instalando dependencias PHP', 600);
            $process->run($run, ['npm', 'ci'], 'Instalando dependencias frontend', 600);
            $process->run($run, ['npm', 'run', 'build'], 'Compilando frontend', 600);
            $process->run($run, ['bash', 'tests/run-php-tests.sh'], 'Ejecutando pruebas PHP', 900);
            $process->run($run, ['bash', 'tests/run-browser-tests.sh'], 'Ejecutando pruebas en Chromium', 1200);
            $process->run($run, ['composer', 'audit'], 'Auditando dependencias PHP', 300);
            $process->run($run, ['npm', 'audit'], 'Auditando dependencias frontend', 300);
            $process->run($run, ['git', 'add', '-A'], 'Preparando cambios', 120, label: 'git add -A');
            $process->run($run, ['git', 'add', '-f', 'public/build'], 'Incluyendo assets compilados', 120, label: 'git add -f public/build');
            $process->run($run, ['git', 'diff', '--cached', '--check'], 'Validando el diff', 120);

            $summary = $process->run(
                $run,
                ['git', 'diff', '--cached', '--name-status'],
                'Generando resumen',
                120,
            )->output();

            $run->update([
                'status' => 'ready',
                'phase' => 'Esperando confirmación',
                'prepared_fingerprint' => $process->fingerprint(),
                'prepared_branch' => $branch,
                'prepared_head_sha' => $headSha,
                'prepared_remote_sha' => $remoteSha,
                'output' => mb_substr(trim(($run->fresh()->output ?? '').PHP_EOL.PHP_EOL.'Archivos preparados:'.PHP_EOL.$summary), -config('deployment.output_limit')),
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($run, $exception);
        }
    }

    private function markFailed(DeploymentRun $run, Throwable $exception): void
    {
        $run->update([
            'status' => 'failed',
            'phase' => 'Preparación fallida',
            'error' => $exception->getMessage(),
            'finished_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        if ($run = DeploymentRun::find($this->deploymentRunId)) {
            $this->markFailed($run, $exception ?? new RuntimeException('El worker de preparación se detuvo inesperadamente.'));
        }
    }
}
