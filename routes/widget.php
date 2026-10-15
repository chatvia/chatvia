<?php

declare(strict_types=1);

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
use App\Http\Controllers\Widget\Conversations\ConversationTitleController;
use App\Http\Controllers\Widget\Forms\SubmitInlineFormController;
use App\Http\Controllers\Widget\Handoff\EndChatController;
use App\Http\Controllers\Widget\Handoff\HandoffRequestController;
use App\Http\Controllers\Widget\Handoff\HandoffStatusController;
use App\Http\Controllers\Widget\Sessions\SessionInitController;
use App\Http\Controllers\Widget\Settings\WidgetSettingsController;
use App\Http\Controllers\Widget\Stream\StreamController;
use App\Http\Controllers\Widget\Voice\EndVoiceCallController;
use App\Http\Controllers\Widget\Voice\StartVoiceCallController;
use App\Http\Controllers\Widget\Voice\TranscribeAudioController;
use App\Http\Middleware\Widget\EnsureWidgetIsAccessible;
use App\Http\Middleware\Widget\VerifyWidgetOrigin;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::prefix('widget')->middleware('throttle:widgets')->group(function (): void {
    // No origin check: agents/verify IS the origin check, answered as
    // `{verified: bool}` for the widget.
    Route::get('/agents/verify', AgentVerifyController::class)->name('widget.agents.verify');

    // Every other widget route must come from one of the agent's allowed sites.
    Route::middleware(VerifyWidgetOrigin::class)->group(function (): void {
        // Bootstrap routes — reachable before sign-in so a protected widget can
        // render its shell, learn it is protected, and run the auth exchange.
        Route::get('/agents/settings', WidgetSettingsController::class)->name('widget.settings');

        // Tighter limit on top of `throttle:widgets` — auth-exchange runs once
        // per page load, so anything beyond a few/minute per IP is abuse.
        Route::post('/auth/exchange', AuthExchangeController::class)
            ->middleware('throttle:widget-exchange')
            ->name('widget.auth.exchange');

        // Chat-data routes — gated by EnsureWidgetIsAccessible: a 404 once both
        // the Chat Widget and the Command Bar are off; for protected agents
        // they also require a valid X-AUTH-TOKEN.
        Route::middleware(EnsureWidgetIsAccessible::class)->group(function (): void {
            Route::post('/sessions/init', SessionInitController::class)->name('widget.sessions.init');

            Route::get('/conversations', ConversationController::class)->name('widget.conversations');
            Route::post('/conversations', ConversationCreateController::class)->name('widget.conversations.create');
            Route::delete('/conversations', ConversationDeleteController::class)->name('widget.conversations.delete');

            Route::post('/conversations/messages', ConversationMessageController::class)->name('widget.conversations.messages.store');

            // Names the conversation right after its first reply: one short
            // LLM call, so under the stream's tighter limit.
            Route::post('/conversations/title', ConversationTitleController::class)
                ->middleware('throttle:widget-stream')
                ->name('widget.conversations.title');
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

            // Voice conversations: like the stream, a call costs the agent's
            // workspace, so starting one has its own tight limit.
            Route::post('/voice', StartVoiceCallController::class)
                ->middleware('throttle:widget-voice')
                ->name('widget.voice.store');
            Route::delete('/voice/calls/{call}', EndVoiceCallController::class)->name('widget.voice.destroy');

            // The composer's mic: speech to text is an AI call too, so it is
            // under the stream's tighter limit.
            Route::post('/transcribe', TranscribeAudioController::class)
                ->middleware('throttle:widget-stream')
                ->name('widget.transcribe');

            Route::post('/feedback', FeedbackController::class)->name('widget.feedback');

            // Inline-form submit (anonymous visitor). {formInstance} binds
            // FormSubmission by external_id; WidgetVerification binds it to the
            // agent + chatter in the controller. SubstituteBindings is applied
            // explicitly here: the widget routes live outside the web/api group, so
            // they don't inherit it — without it {formInstance} would reach the
            // controller as an empty model (agent_id null) and 404 on the guard.
            Route::post('/forms/{formInstance:external_id}/submit', SubmitInlineFormController::class)
                ->middleware(['throttle:widget-stream', SubstituteBindings::class])
                ->name('widget.forms.submit');

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
});
