<?php

use App\Http\Controllers\Widget\WidgetAgentVerifyController;
use App\Http\Controllers\Widget\WidgetLeadCollectController;
use App\Http\Controllers\Widget\WidgetLeadFormController;
use App\Http\Controllers\Widget\WidgetSessionStoreController;
use App\Http\Controllers\Widget\WidgetSettingsController;
use App\Http\Controllers\Widget\WidgetStreamController;
use App\Http\Controllers\Widget\WidgetThreadMessagesController;
use App\Http\Controllers\Widget\WidgetThreadsController;
use App\Http\Controllers\Widget\WidgetThreadsDeleteController;
use App\Http\Controllers\Widget\WidgetThreadsStoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('widget')->group(function () {
    Route::post('/sessions', WidgetSessionStoreController::class)->name('widget.sessions');

    Route::get('/threads', WidgetThreadsController::class)->name('widget.threads');

    Route::post('/threads', WidgetThreadsStoreController::class)->name('widget.threads.store');

    Route::delete('/threads', WidgetThreadsDeleteController::class)->name('widget.threads.delete');

    Route::post('/threads/messages', WidgetThreadMessagesController::class)->name('widget.threads.messages.store');

    Route::get('/threads/messages', WidgetThreadMessagesController::class)->name('widget.threads.messages');

    Route::get('/agents/verify', WidgetAgentVerifyController::class)->name('widget.agents.verify');

    Route::post('/stream', WidgetStreamController::class)->name('widget.stream');

    Route::get('/agents/settings', WidgetSettingsController::class)->name('widget.settings');

    Route::get('/leads/form', WidgetLeadFormController::class)->name('widget.leads.form');

    Route::post('/leads/form', WidgetLeadCollectController::class)->name('widget.leads.collect');
});
