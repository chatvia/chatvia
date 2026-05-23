<?php

if (app()->isLocal()) {
    Route::get('playground', function () {
        dd(app(\App\Services\TopicAnalysis::class)->analyze('How to login?'));
    });

    // Test webhook for the Functions feature. Returns a fixed value so we
    // can verify the end-to-end LLM tool-call loop without standing up an
    // external API. Path lives under /webhooks/* which is excluded from
    // CSRF in bootstrap/app.php.
    Route::match(['get', 'post'], '/webhooks/get-weather', function () {
        sleep(5);
        return response()->json([
            'weather' => 23,
            'unit' => 'celsius',
        ]);
    })->name('webhooks.get-weather');
}
