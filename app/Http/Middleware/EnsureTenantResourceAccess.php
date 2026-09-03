<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantResourceAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$parameters): Response
    {
        foreach ($parameters as $parameter) {
            $resource = $request->route($parameter);

            if (! $resource instanceof Model) {
                abort(404);
            }

            Gate::authorize('view', $resource);
        }

        return $next($request);
    }
}
