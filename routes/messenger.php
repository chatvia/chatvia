<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\MessengerWebhookController;
use App\Http\Controllers\Webhooks\VerifyMessengerWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Messenger Platform webhook for Chatvia's single Meta app — one
 * app-level Callback URL (no per-page secret). GET is Meta's verify handshake
 * (echo hub.challenge against the app-level verify token), POST carries events
 * verified by the X-Hub-Signature-256 HMAC against the app secret and routed
 * to the agent by Page id. No session / CSRF (bare routes).
 *
 * Throttled per source IP with a high cap: the provider sends every tenant's
 * events from a small pool of IPs, so the limit only stops a flood from one
 * non-provider address and must never slow real traffic.
 */
Route::get('messenger/webhook', VerifyMessengerWebhookController::class)
    ->middleware('throttle:60,1,messenger-webhook-verify')
    ->name('messenger.webhook.verify');
Route::post('messenger/webhook', MessengerWebhookController::class)
    ->middleware('throttle:1200,1,messenger-webhook')
    ->name('messenger.webhook');
