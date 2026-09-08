<?php

use App\Jobs\PrepareDeployment;
use App\Jobs\RunProductionDeployment;
use App\Models\DeploymentRun;
use App\Models\Store;
use App\Models\User;
use App\Services\DeploymentAuthorizer;
use App\Services\DeploymentProcess;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function deploymentUser(string $email = 'cristian.ospinagarcia@gmail.com', string $storeName = 'la aguacatera'): User
{
    $user = User::factory()->create([
        'email' => $email,
        'email_verified_at' => now(),
        'is_admin' => true,
    ]);
    $store = Store::create([
        'name' => $storeName,
        'slug' => str($storeName)->slug(),
        'user_id' => $user->id,
        'plan' => 'negociante',
    ]);
    $user->update(['store_id' => $store->id]);

    return $user->fresh('store');
}

beforeEach(function () {
    config()->set('deployment.enabled', true);
    config()->set('deployment.allow_testing', true);
});

it('hides deployment routes from guests and other administrators', function () {
    $this->get(route('admin.deployments.index'))->assertRedirect(route('login'));

    $otherUser = deploymentUser('other@example.test');

    $this->actingAs($otherUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.deployments.index'))
        ->assertNotFound();
});

it('shows the deployment panel to the configured owner', function () {
    $user = deploymentUser();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.deployments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Deployments/Index')
            ->where('auth.canDeploy', true));
});

it('allows only the configured owner to prepare a deployment locally', function () {
    Queue::fake();
    $user = deploymentUser();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.deployments.prepare'))
        ->assertRedirect(route('admin.deployments.index'));

    $run = DeploymentRun::sole();

    expect($run->user_id)->toBe($user->id)
        ->and($run->store_id)->toBe($user->store_id)
        ->and($run->status)->toBe('preparing');

    Queue::assertPushed(PrepareDeployment::class, fn (PrepareDeployment $job) => $job->deploymentRunId === $run->id);
});

it('requires recent password confirmation before preparing', function () {
    Queue::fake();
    $user = deploymentUser();

    $this->actingAs($user)
        ->post(route('admin.deployments.prepare'))
        ->assertRedirect(route('password.confirm'));

    expect(DeploymentRun::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('requires the exact phrase and queues only an owned ready run', function () {
    Queue::fake();
    $user = deploymentUser();
    $run = DeploymentRun::create([
        'user_id' => $user->id,
        'store_id' => $user->store_id,
        'status' => 'ready',
        'phase' => 'Esperando confirmación',
        'prepared_fingerprint' => str_repeat('a', 64),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.deployments.confirm', $run), [
            'commit_message' => 'deploy: prueba segura',
            'confirmation' => 'deploy',
        ])
        ->assertSessionHasErrors('confirmation');

    $this->post(route('admin.deployments.confirm', $run), [
        'commit_message' => 'deploy: prueba segura',
        'confirmation' => 'DESPLEGAR',
    ])->assertRedirect(route('admin.deployments.index'));

    expect($run->fresh()->status)->toBe('queued');
    Queue::assertPushed(RunProductionDeployment::class, fn (RunProductionDeployment $job) => $job->deploymentRunId === $run->id);
});

it('is disabled outside the local environment', function () {
    $user = deploymentUser();
    config()->set('deployment.allow_testing', false);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('http://example.com/admin/deployments')
        ->assertNotFound();
});

it('fails closed before deployment when deployment credentials are unavailable', function () {
    $user = deploymentUser();
    $run = DeploymentRun::create([
        'user_id' => $user->id,
        'store_id' => $user->store_id,
        'status' => 'queued',
        'phase' => 'En cola',
    ]);
    config()->set('deployment.git_identity_file', storage_path('missing-github-key'));

    (new RunProductionDeployment($run->id))->handle(app(DeploymentProcess::class), app(DeploymentAuthorizer::class));

    expect($run->fresh()->status)->toBe('failed')
        ->and($run->fresh()->error)->toContain('git_identity_file');
});

it('lets the owner discard a ready preparation', function () {
    $user = deploymentUser();
    $run = DeploymentRun::create([
        'user_id' => $user->id,
        'store_id' => $user->store_id,
        'status' => 'ready',
        'phase' => 'Esperando confirmación',
        'prepared_fingerprint' => str_repeat('a', 64),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.deployments.discard', $run))
        ->assertRedirect(route('admin.deployments.index'));

    expect($run->fresh()->status)->toBe('cancelled');
});
