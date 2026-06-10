<?php

use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public WhatsApp Cloud API webhook. The {secret} is the per-number
 * `webhook_secret` (routes the event to the right agent); GET is Meta's
 * verify handshake (echo hub.challenge), POST carries events verified by the
 * X-Hub-Signature-256 HMAC against the app secret. No session / CSRF (bare
 * routes).
 */
Route::get('whatsapp/webhook/{secret}', [WhatsappWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('whatsapp/webhook/{secret}', [WhatsappWebhookController::class, 'receive'])->name('whatsapp.webhook');
