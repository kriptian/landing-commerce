<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class DeploymentAuthorizer
{
    public function allows(?User $user, ?Request $request = null): bool
    {
        $localRequest = $request !== null
            && in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true);
        $allowedEnvironment = app()->environment('local')
            || (app()->environment('testing') && config('deployment.allow_testing'));

        if ((! $localRequest && (! config('deployment.enabled') || ! $allowedEnvironment)) || ! $user) {
            return false;
        }

        $store = $user->store;

        return $store !== null
            && strtolower(trim($user->email)) === strtolower(config('deployment.allowed_email'))
            && $store->slug === config('deployment.allowed_store_slug')
            && (int) $store->user_id === (int) $user->id;
    }
}
