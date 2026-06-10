<?php

use App\Http\Controllers\Webhooks\InstagramWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Instagram Messaging webhook. The {secret} is the per-account
 * `webhook_secret` (routes the event to the right agent); GET is Meta's
 * verify handshake (echo hub.challenge), POST carries events verified by the
 * X-Hub-Signature-256 HMAC against the Instagram app secret. No session /
 * CSRF (bare routes).
 */
Route::get('instagram/webhook/{secret}', [InstagramWebhookController::class, 'verify'])->name('instagram.webhook.verify');
Route::post('instagram/webhook/{secret}', [InstagramWebhookController::class, 'receive'])->name('instagram.webhook');
