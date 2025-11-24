<?php

if (app()->isLocal()) {
    Route::get('playground', function () {
        dd(app(\App\Services\TopicAnalysis::class)->analyze('How to login?'));
    });

}
