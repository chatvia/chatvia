<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;

if (app()->isLocal()) {

    // Test webhook for the Functions feature. Returns a fake reading so we
    // can verify the end-to-end LLM tool-call loop without standing up an
    // external API. The tool runner posts here server-to-server, without a
    // CSRF token, so this one route skips the CSRF check.
    Route::match(['get', 'post'], '/webhooks/get-weather', function (Request $request) {
        logger()->info(
            json_encode($request->all(), JSON_PRETTY_PRINT)
        );

        return response()->json([
            'weather' => mt_rand(20, 50),
            'unit' => 'celsius',
        ]);
    })->withoutMiddleware(PreventRequestForgery::class)->name('webhooks.get-weather');
}
