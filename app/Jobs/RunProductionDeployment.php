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

class RunProductionDeployment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public int $timeout = 7200;

    public bool $failOnTimeout = true;

    public function __construct(public int $deploymentRunId)
    {
        $this->onConnection('database')->onQueue('deployments');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('production-deployment'))->releaseAfter(5)->expireAfter(7300)->shared()];
    }

    public function handle(DeploymentProcess $process, DeploymentAuthorizer $authorizer): void
    {
        $run = DeploymentRun::findOrFail($this->deploymentRunId);

        if ($run->status !== 'queued') {
            return;
        }

        $run->update([
            'status' => 'running',
            'phase' => 'Verificando cambios',
            'started_at' => now(),
            'finished_at' => null,
            'error' => null,
        ]);

        try {
            if (! $authorizer->allows($run->user) || (int) $run->store_id !== (int) $run->user->store_id) {
                throw new RuntimeException('La autorización de despliegue ya no es válida.');
            }

            $this->assertConfiguration();
            $this->runPreflight($run, $process);

            $branch = trim($process->run($run, ['git', 'branch', '--show-current'], 'Verificando rama', 60)->output());

            if ($branch !== config('deployment.branch')) {
                throw new RuntimeException('El despliegue solo puede ejecutarse desde la rama '.config('deployment.branch').'.');
            }

            if ($branch !== $run->prepared_branch) {
                throw new RuntimeException('La rama cambió después de la preparación.');
            }

            $headSha = trim($process->run($run, ['git', 'rev-parse', 'HEAD'], 'Verificando commit local', 60)->output());

            if (! hash_equals((string) $run->prepared_head_sha, $headSha)) {
                throw new RuntimeException('El commit local cambió después de la preparación.');
            }

            $process->run($run, ['git', 'add', '-A'], 'Verificando archivos preparados', 120, label: 'git add -A');
            $process->run($run, ['git', 'add', '-f', 'public/build'], 'Verificando assets preparados', 120, label: 'git add -f public/build');

            if (! hash_equals((string) $run->prepared_fingerprint, $process->fingerprint())) {
                throw new RuntimeException('Los archivos cambiaron después de la preparación. Prepara el despliegue nuevamente.');
            }

            $process->run($run, ['git', 'fetch', 'origin', config('deployment.branch')], 'Actualizando referencia remota', 300);
            $remoteSha = trim($process->run(
                $run,
                ['git', 'rev-parse', 'refs/remotes/origin/'.config('deployment.branch')],
                'Verificando commit remoto',
                60,
            )->output());

            if (! hash_equals((string) $run->prepared_remote_sha, $remoteSha)) {
                throw new RuntimeException('origin/main cambió después de la preparación. Prepara el despliegue nuevamente.');
            }

            $previousSha = trim($process->run($run, ['git', 'rev-parse', 'HEAD'], 'Leyendo versión actual', 60)->output());
            $run->update(['previous_sha' => $previousSha]);

            $staged = Process::path(base_path())->timeout(60)->run(['git', 'diff', '--cached', '--quiet']);

            if ($staged->exitCode() === 1) {
                $process->run($run, [
                    'git',
                    '-c',
                    'core.hooksPath=/dev/null',
                    '-c',
                    'user.name=Cristian Ospina',
                    '-c',
                    'user.email=cristian.ospinagarcia@gmail.com',
                    'commit',
                    '-m',
                    $run->commit_message,
                ], 'Creando commit', 180, label: 'git commit');
            } elseif ($staged->failed()) {
                throw new RuntimeException('No se pudo verificar el estado de los cambios preparados.');
            }

            try {
                $process->run(
                    $run,
                    ['git', '-c', 'core.hooksPath=/dev/null', 'rebase', $remoteSha],
                    'Sincronizando con la referencia verificada',
                    300,
                );
            } catch (Throwable $exception) {
                Process::path(base_path())->timeout(60)->run(['git', 'rebase', '--abort']);
                throw $exception;
            }

            $gitSsh = $this->sshCommand(config('deployment.git_identity_file'));
            $process->run(
                $run,
                ['git', 'push', config('deployment.repository_ssh'), 'HEAD:refs/heads/'.config('deployment.branch')],
                'Publicando en GitHub',
                300,
                ['GIT_SSH_COMMAND' => $gitSsh],
                'git push origin main',
            );

            $sha = trim($process->run($run, ['git', 'rev-parse', 'HEAD'], 'Confirmando commit', 60)->output());
            $run->update(['commit_sha' => $sha]);

            $process->run(
                $run,
                [
                    'ssh',
                    '-i', config('deployment.ssh_identity_file'),
                    '-o', 'IdentitiesOnly=yes',
                    '-o', 'BatchMode=yes',
                    '-o', 'StrictHostKeyChecking=yes',
                    '-o', 'UserKnownHostsFile='.config('deployment.known_hosts_file'),
                    '-p', (string) config('deployment.remote_port'),
                    config('deployment.remote_user').'@'.config('deployment.remote_host'),
                    config('deployment.remote_script'),
                    $sha,
                ],
                'Desplegando en producción',
                config('deployment.timeout'),
                label: 'ssh Hostinger deploy',
            );

            $run->update([
                'status' => 'succeeded',
                'phase' => 'Despliegue completado',
                'exit_code' => 0,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'phase' => 'Despliegue fallido',
                'error' => $exception->getMessage(),
                'exit_code' => $exception->getCode() ?: 1,
                'finished_at' => now(),
            ]);
        }
    }

    private function assertConfiguration(): void
    {
        foreach (['git_identity_file', 'ssh_identity_file', 'known_hosts_file'] as $key) {
            if (! is_readable(config('deployment.'.$key))) {
                throw new RuntimeException("Falta configurar {$key} para el worker de despliegue.");
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($run = DeploymentRun::find($this->deploymentRunId)) {
            $run->update([
                'status' => 'failed',
                'phase' => 'El worker de despliegue se detuvo',
                'error' => $exception?->getMessage() ?? 'El worker de despliegue se detuvo inesperadamente.',
                'exit_code' => 1,
                'finished_at' => now(),
            ]);
        }
    }

    private function sshCommand(string $identityFile): string
    {
        return implode(' ', [
            'ssh',
            '-i', escapeshellarg($identityFile),
            '-o', 'IdentitiesOnly=yes',
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=yes',
            '-o', 'UserKnownHostsFile='.escapeshellarg(config('deployment.known_hosts_file')),
        ]);
    }

    private function runPreflight(DeploymentRun $run, DeploymentProcess $process): void
    {
        $gitSsh = $this->sshCommand(config('deployment.git_identity_file'));

        $process->run(
            $run,
            ['git', 'ls-remote', config('deployment.repository_ssh'), 'HEAD'],
            'Verificando acceso a GitHub',
            60,
            ['GIT_SSH_COMMAND' => $gitSsh],
            'git ls-remote GitHub',
        );

        $process->run(
            $run,
            ['git', 'push', '--dry-run', config('deployment.repository_ssh'), 'origin/main:refs/heads/'.config('deployment.branch')],
            'Verificando acceso de escritura a GitHub',
            120,
            ['GIT_SSH_COMMAND' => $gitSsh],
            'git push --dry-run origin main',
        );

        $process->run(
            $run,
            [
                'ssh',
                '-i', config('deployment.ssh_identity_file'),
                '-o', 'IdentitiesOnly=yes',
                '-o', 'BatchMode=yes',
                '-o', 'StrictHostKeyChecking=yes',
                '-o', 'UserKnownHostsFile='.config('deployment.known_hosts_file'),
                '-p', (string) config('deployment.remote_port'),
                config('deployment.remote_user').'@'.config('deployment.remote_host'),
                'test',
                '-x',
                config('deployment.remote_script'),
            ],
            'Verificando acceso a Hostinger',
            60,
            label: 'ssh Hostinger preflight',
        );
    }
}
