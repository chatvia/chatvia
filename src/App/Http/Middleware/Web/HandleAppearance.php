<?php

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Support\Facades\View;

class HandleAppearance
{
    public function handle($request, Closure $next)
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');

        return $next($request);
    }
}
