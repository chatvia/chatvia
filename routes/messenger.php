<?php

use App\Http\Controllers\Webhooks\MessengerWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Messenger Platform webhook. The {secret} is the per-page
 * `webhook_secret` (routes the event to the right agent); GET is Meta's
 * verify handshake (echo hub.challenge), POST carries events verified by the
 * X-Hub-Signature-256 HMAC against the app secret. No session / CSRF (bare
 * routes).
 */
Route::get('messenger/webhook/{secret}', [MessengerWebhookController::class, 'verify'])->name('messenger.webhook.verify');
Route::post('messenger/webhook/{secret}', [MessengerWebhookController::class, 'receive'])->name('messenger.webhook');
