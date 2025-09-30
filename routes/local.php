<?php

if (app()->isLocal()) {
    Route::get('playground', function () {
        \Illuminate\Support\Facades\Storage::put('playground.txt', 'Hello, Playground!');
    });
}
