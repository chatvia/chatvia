<?php

namespace App\Providers;

use App\Http\Macros\RequestMacros;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the request macros ({@see RequestMacros}). They are added in
 * register() so middleware and other providers can use them while booting.
 */
class HttpMacroServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        RequestMacros::register();
    }
}
