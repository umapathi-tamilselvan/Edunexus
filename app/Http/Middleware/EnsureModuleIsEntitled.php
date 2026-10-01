<?php

namespace App\Http\Middleware;

use App\Support\Modules\Entitlements;
use App\Support\Modules\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a module's routes: `->middleware('entitled:attendance')`.
 */
class EnsureModuleIsEntitled
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! $this->entitlements->allows(Module::from($module))) {
            abort(403, 'This module is not enabled for your institution.');
        }

        return $next($request);
    }
}
