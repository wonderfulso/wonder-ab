<?php

namespace Wonderfulso\WonderAb\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WonderAbAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authType = config('wonder-ab.report_auth', 'none');

        return match ($authType) {
            'none' => $next($request),
            'basic' => $this->handleBasicAuth($request, $next),
            'closure' => $this->handleClosureAuth($request, $next),
            'middleware' => $next($request), // Handled by their middleware stack
            default => abort(403, 'Invalid authentication configuration')
        };
    }

    /**
     * Handle HTTP Basic Authentication
     */
    protected function handleBasicAuth(Request $request, Closure $next): Response
    {
        $username = config('wonder-ab.report_username');
        $password = config('wonder-ab.report_password');

        if (empty($username) || empty($password)) {
            abort(500, 'Basic auth configured but credentials not set');
        }

        $user = $request->getUser();
        $pass = $request->getPassword();

        if ($user !== $username || $pass !== $password) {
            return response('Unauthorized', 401)
                ->header('WWW-Authenticate', 'Basic realm="AB Reports"')
                ->header('Cache-Control', 'no-cache, must-revalidate, max-age=0');
        }

        return $next($request);
    }

    /**
     * Handle Closure-based Authentication
     */
    protected function handleClosureAuth(Request $request, Closure $next): Response
    {
        $callback = config('wonder-ab.report_auth_callback');

        if (! is_callable($callback)) {
            abort(500, 'Closure auth configured but callback not set');
        }

        if (! $callback($request)) {
            abort(403, 'Unauthorized access to AB reports');
        }

        return $next($request);
    }
}
