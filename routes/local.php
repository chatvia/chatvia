<?php

use App\Models\Agent;

if (app()->isLocal()) {
    Route::get('playground', function () {
        do {
            $externalId = uniqid('', false);
        } while (Agent::where('external_id', $externalId)->exists());

    });
}
