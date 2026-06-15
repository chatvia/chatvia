<?php

use App\Http\Controllers\Webhooks\MessengerWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Messenger Platform webhook for Chatvia's single Meta app — one
 * app-level Callback URL (no per-page secret). GET is Meta's verify handshake
 * (echo hub.challenge against the app-level verify token), POST carries events
 * verified by the X-Hub-Signature-256 HMAC against the app secret and routed
 * to the agent by Page id. No session / CSRF (bare routes).
 */
Route::get('messenger/webhook', [MessengerWebhookController::class, 'verify'])->name('messenger.webhook.verify');
Route::post('messenger/webhook', [MessengerWebhookController::class, 'receive'])->name('messenger.webhook');
