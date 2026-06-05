<?php

use App\Http\Controllers\Widget\Activity\FeedbackController;
use App\Http\Controllers\Widget\Activity\HeartbeatController;
use App\Http\Controllers\Widget\Activity\MarkReadController;
use App\Http\Controllers\Widget\Activity\TypingController;
use App\Http\Controllers\Widget\Auth\AgentVerifyController;
use App\Http\Controllers\Widget\Auth\AuthExchangeController;
use App\Http\Controllers\Widget\Auth\BroadcastAuthController;
use App\Http\Controllers\Widget\Conversations\ConversationAttachmentController;
use App\Http\Controllers\Widget\Conversations\ConversationController;
use App\Http\Controllers\Widget\Conversations\ConversationCreateController;
use App\Http\Controllers\Widget\Conversations\ConversationDeleteController;
use App\Http\Controllers\Widget\Conversations\ConversationMessageController;
use App\Http\Controllers\Widget\Handoff\EndChatController;
use App\Http\Controllers\Widget\Handoff\HandoffRequestController;
use App\Http\Controllers\Widget\Handoff\HandoffStatusController;
use App\Http\Controllers\Widget\Sessions\SessionInitController;
use App\Http\Controllers\Widget\Settings\WidgetSettingsController;
use App\Http\Controllers\Widget\Stream\StreamController;
use App\Http\Middleware\Widget\EnsureWidgetIsAccessible;
use Illuminate\Support\Facades\Route;

Route::prefix('widget')->middleware('throttle:widgets')->group(function (): void {
    // Bootstrap routes — reachable before sign-in so a protected widget can
    // render its shell, learn it is protected, and run the auth exchange.
    Route::get('/agents/verify', AgentVerifyController::class)->name('widget.agents.verify');

    Route::get('/agents/settings', WidgetSettingsController::class)->name('widget.settings');

    // Tighter limit on top of `throttle:widgets` — auth-exchange runs once
    // per page load, so anything beyond a few/minute per IP is abuse.
    Route::post('/auth/exchange', AuthExchangeController::class)
        ->middleware('throttle:widget-exchange')
        ->name('widget.auth.exchange');

    Route::get('/version', function () {
        return response()->json(['version' => config('app.version')]);
    })->name('widget.version');

    // Chat-data routes — gated by EnsureWidgetIsAccessible. No-op for public
    // agents; for protected agents they require a valid X-AUTH-TOKEN.
    Route::middleware(EnsureWidgetIsAccessible::class)->group(function (): void {
        Route::post('/sessions/init', SessionInitController::class)->name('widget.sessions.init');

        Route::get('/conversations', ConversationController::class)->name('widget.conversations');
        Route::post('/conversations', ConversationCreateController::class)->name('widget.conversations.create');
        Route::delete('/conversations', ConversationDeleteController::class)->name('widget.conversations.delete');

        Route::post('/conversations/messages', ConversationMessageController::class)->name('widget.conversations.messages.store');
        Route::get('/conversations/messages', ConversationMessageController::class)->name('widget.conversations.messages');

        // Visitor uploads (PDFs for now). Returns a public URL the LLM-side
        // tools (PDF Reader, etc.) can fetch.
        Route::post('/conversations/attachments', ConversationAttachmentController::class)
            ->middleware('throttle:widget-stream')
            ->name('widget.conversations.attachments.store');

        // Stream is the LLM-cost endpoint — separate, tighter, session-
        // keyed limiter on top of the bulk widget gate.
        Route::post('/stream', StreamController::class)
            ->middleware('throttle:widget-stream')
            ->name('widget.stream');

        Route::post('/feedback', FeedbackController::class)->name('widget.feedback');

        // WebSocket / handoff endpoints
        Route::post('/broadcasting/auth', BroadcastAuthController::class)->name('widget.broadcasting.auth');
        Route::post('/typing', TypingController::class)->name('widget.typing');
        Route::post('/handoff/request', HandoffRequestController::class)->name('widget.handoff.request');
        Route::get('/handoff/status', HandoffStatusController::class)->name('widget.handoff.status');
        Route::post('/handoff/end', EndChatController::class)->name('widget.handoff.end');
        Route::post('/heartbeat', HeartbeatController::class)->name('widget.heartbeat');
        Route::post('/mark-read', MarkReadController::class)->name('widget.mark-read');
    });
});
