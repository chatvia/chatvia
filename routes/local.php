<?php

if (app()->isLocal()) {
    Route::get('playground', function () {
        return app(\App\Support\Subdomain::class)->generateRandomSubDomain();
    });
}
