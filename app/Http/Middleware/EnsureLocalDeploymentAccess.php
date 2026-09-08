<?php

namespace App\Http\Middleware;

use App\Services\DeploymentAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalDeploymentAccess
{
    public function __construct(private DeploymentAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->authorizer->allows($request->user('web'), $request), 404);

        return $next($request);
    }
}
