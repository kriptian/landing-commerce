<?php

namespace App\Services;

use App\Models\DeploymentRun;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class DeploymentProcess
{
    public function run(
        DeploymentRun $run,
        array $command,
        string $phase,
        ?int $timeout = null,
        array $environment = [],
        ?string $label = null,
    ): ProcessResult {
        $run->update(['phase' => $phase]);
        $this->append($run, '$ '.($label ?? implode(' ', $command)));

        $result = Process::path(base_path())
            ->timeout($timeout ?? config('deployment.timeout'))
            ->env($environment)
            ->run($command);

        $this->append($run, trim($result->output().PHP_EOL.$result->errorOutput()));

        if ($result->failed()) {
            throw new RuntimeException("Falló {$phase} con código {$result->exitCode()}.");
        }

        return $result;
    }

    public function append(DeploymentRun $run, string $text): void
    {
        if ($text === '') {
            return;
        }

        $output = trim(($run->fresh()->output ?? '').PHP_EOL.$text);
        $limit = config('deployment.output_limit');

        $run->update(['output' => mb_substr($output, -$limit)]);
    }

    public function fingerprint(): string
    {
        $result = Process::path(base_path())
            ->timeout(120)
            ->run(['git', 'diff', '--cached', '--binary']);

        if ($result->failed()) {
            throw new RuntimeException('No se pudo calcular la huella de los cambios preparados.');
        }

        return hash('sha256', $result->output());
    }
}
