<?php

namespace Wonderfulso\WonderAb\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Wonderfulso\WonderAb\Facades\Ab;

class WonderAbMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        Ab::initUser($request);

        $response = $next($request);

        $cookie = Ab::saveSession();
        if (method_exists($response, 'withCookie')) {
            return $response->withCookie(cookie()->forever(config('wonder-ab.cache_key'), $cookie));
        }

        return $response;
    }
}
