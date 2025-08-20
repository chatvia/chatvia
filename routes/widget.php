<?php

use App\Http\Controllers\Widget\WidgetAgentVerifyController;
use App\Http\Controllers\Widget\WidgetSessionStoreController;
use App\Http\Controllers\Widget\WidgetSettingsController;
use App\Http\Controllers\Widget\WidgetStreamController;
use App\Http\Controllers\Widget\WidgetThreadsController;
use App\Http\Controllers\Widget\WidgetThreadsStoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('widget')->group(function () {
    Route::post('/sessions', WidgetSessionStoreController::class)->name('widget.sessions');

    Route::get('/threads', WidgetThreadsController::class)->name('widget.threads');

    Route::post('/threads', WidgetThreadsStoreController::class)->name('widget.threads.store');

    Route::post('/threads/messages', function () {
        return ['id' => \Illuminate\Support\Str::uuid()->toString()];
    });

    Route::get('/threads/messages', function () {
        return [];
    });

    Route::get('/agents/verify', WidgetAgentVerifyController::class)->name('widget.agents.verify');

    Route::post('/stream', WidgetStreamController::class)->name('widget.stream');

    Route::get('/agents/settings', WidgetSettingsController::class)->name('widget.settings');
});
