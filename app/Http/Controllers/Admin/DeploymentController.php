<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PrepareDeployment;
use App\Jobs\RunProductionDeployment;
use App\Models\DeploymentRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeploymentController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Deployments/Index', [
            'runs' => DeploymentRun::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (DeploymentRun $run) => $this->serialize($run)),
        ]);
    }

    public function prepare(Request $request): RedirectResponse
    {
        $lock = Cache::lock('deployment-create', 10);

        if (! $lock->get()) {
            throw ValidationException::withMessages(['deployment' => 'Ya se está iniciando otro despliegue.']);
        }

        try {
            if (DeploymentRun::whereIn('status', DeploymentRun::ACTIVE_STATUSES)->exists()) {
                throw ValidationException::withMessages(['deployment' => 'Ya existe un despliegue activo.']);
            }

            DB::transaction(function () use ($request): void {
                $run = DeploymentRun::create([
                    'user_id' => $request->user()->id,
                    'store_id' => $request->user()->store_id,
                    'status' => 'preparing',
                    'phase' => 'En cola para preparación',
                ]);

                PrepareDeployment::dispatch($run->id);
            });

            return redirect()->route('admin.deployments.index')
                ->with('success', 'La preparación del despliegue comenzó.');
        } finally {
            $lock->release();
        }
    }

    public function confirm(Request $request, DeploymentRun $deploymentRun): RedirectResponse
    {
        $this->authorizeRun($request, $deploymentRun);

        $validated = $request->validate([
            'commit_message' => ['required', 'string', 'min:5', 'max:160'],
            'confirmation' => ['required', 'in:DESPLEGAR'],
        ]);

        DB::transaction(function () use ($request, $deploymentRun, $validated): void {
            $run = DeploymentRun::lockForUpdate()->findOrFail($deploymentRun->id);
            $this->authorizeRun($request, $run);

            if ($run->status !== 'ready') {
                throw ValidationException::withMessages(['deployment' => 'Este despliegue ya no está listo para confirmar.']);
            }

            $run->update([
                'status' => 'queued',
                'phase' => 'En cola para producción',
                'commit_message' => $validated['commit_message'],
            ]);

            RunProductionDeployment::dispatch($run->id);
        });

        return redirect()->route('admin.deployments.index')
            ->with('success', 'El despliegue fue confirmado.');
    }

    public function show(Request $request, DeploymentRun $deploymentRun): JsonResponse
    {
        $this->authorizeRun($request, $deploymentRun);

        return response()->json($this->serialize($deploymentRun->fresh()));
    }

    public function discard(Request $request, DeploymentRun $deploymentRun): RedirectResponse
    {
        $this->authorizeRun($request, $deploymentRun);

        DB::transaction(function () use ($request, $deploymentRun): void {
            $run = DeploymentRun::lockForUpdate()->findOrFail($deploymentRun->id);
            $this->authorizeRun($request, $run);

            if ($run->status !== 'ready') {
                throw ValidationException::withMessages(['deployment' => 'Solo se puede descartar una preparación lista.']);
            }

            $run->update([
                'status' => 'cancelled',
                'phase' => 'Preparación descartada',
                'finished_at' => now(),
            ]);
        });

        return redirect()->route('admin.deployments.index');
    }

    private function authorizeRun(Request $request, DeploymentRun $run): void
    {
        abort_unless(
            (int) $run->user_id === (int) $request->user()->id
            && (int) $run->store_id === (int) $request->user()->store_id,
            404,
        );
    }

    private function serialize(DeploymentRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'phase' => $run->phase,
            'commit_message' => $run->commit_message,
            'commit_sha' => $run->commit_sha,
            'previous_sha' => $run->previous_sha,
            'output' => $run->output,
            'error' => $run->error,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }
}
