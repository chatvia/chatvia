<?php

use Illuminate\Http\Request;

if (app()->isLocal()) {

    Route::get('test', function (Request $request): array {
        logger()->info(print_r($request->headers->all(), true));

        return [
            'order_id' => 123,
            'status' => 'shipped',
            'headers' => print_r($request->headers->all(), true),
        ];
    });

    // Test webhook for the Functions feature. Returns a fixed value so we
    // can verify the end-to-end LLM tool-call loop without standing up an
    // external API. Path lives under /webhooks/* which is excluded from
    // CSRF in bootstrap/app.php.
    Route::match(['get', 'post'], '/webhooks/get-weather', function (Request $request) {
        logger()->info(
            json_encode($request->all(), JSON_PRETTY_PRINT)
        );
        sleep(5);

        return response()->json([
            'weather' => mt_rand(20, 50),
            'unit' => 'celsius',
        ]);
    })->name('webhooks.get-weather');
}
