<?php

if (app()->isLocal()) {
    Route::get('playground', function () {
        $d = \App\Models\SourceWebsite::first();
    });

}
