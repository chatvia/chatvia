<?php

use App\Http\Controllers\Widget\WidgetAgentVerifyController;
use App\Http\Controllers\Widget\WidgetBroadcastAuthController;
use App\Http\Controllers\Widget\WidgetEndChatController;
use App\Http\Controllers\Widget\WidgetFeedbackController;
use App\Http\Controllers\Widget\WidgetHandoffRequestController;
use App\Http\Controllers\Widget\WidgetHandoffStatusController;
use App\Http\Controllers\Widget\WidgetHeartbeatController;
use App\Http\Controllers\Widget\WidgetLeadCollectController;
use App\Http\Controllers\Widget\WidgetLeadFormController;
use App\Http\Controllers\Widget\WidgetMarkReadController;
use App\Http\Controllers\Widget\WidgetSessionInitController;
use App\Http\Controllers\Widget\WidgetSettingsController;
use App\Http\Controllers\Widget\WidgetStreamController;
use App\Http\Controllers\Widget\WidgetThreadMessagesController;
use App\Http\Controllers\Widget\WidgetThreadsController;
use App\Http\Controllers\Widget\WidgetThreadsDeleteController;
use App\Http\Controllers\Widget\WidgetThreadsStoreController;
use App\Http\Controllers\Widget\WidgetTypingController;
use Illuminate\Support\Facades\Route;

Route::prefix('widget')->group(function () {
    Route::post('/sessions/init', WidgetSessionInitController::class)->name('widget.sessions.init');

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

    Route::post('/feedback', WidgetFeedbackController::class)->name('widget.feedback');

    // WebSocket/Handoff endpoints
    Route::post('/broadcasting/auth', WidgetBroadcastAuthController::class)->name('widget.broadcasting.auth');
    Route::post('/typing', WidgetTypingController::class)->name('widget.typing');
    Route::post('/handoff/request', WidgetHandoffRequestController::class)->name('widget.handoff.request');
    Route::get('/handoff/status', WidgetHandoffStatusController::class)->name('widget.handoff.status');
    Route::post('/handoff/end', WidgetEndChatController::class)->name('widget.handoff.end');
    Route::post('/heartbeat', WidgetHeartbeatController::class)->name('widget.heartbeat');
    Route::post('/mark-read', WidgetMarkReadController::class)->name('widget.mark-read');

    Route::get('/version', function () {
        return response()->json([
            'version' => config('app.version'),
        ]);
    })->name('widget.version');
});
