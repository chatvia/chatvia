<?php

if (app()->isLocal()) {
    Route::get('playground', function () {
        $agent = \App\Models\Agent::query()->whereExternalId('407f7fdfa1cf18')->first();
        dd($agent->leads);
    });
}
