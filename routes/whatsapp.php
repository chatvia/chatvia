<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public WhatsApp Cloud API webhook for Chatvia's single Meta app — one
 * app-level Callback URL (no per-number secret). GET is Meta's verify handshake
 * (echo hub.challenge against the app-level verify token), POST carries events
 * verified by the X-Hub-Signature-256 HMAC against the app secret and routed to
 * the agent by the phone number id. No session / CSRF (bare routes).
 *
 * Throttled per source IP with a high cap: the provider sends every tenant's
 * events from a small pool of IPs, so the limit only stops a flood from one
 * non-provider address and must never slow real traffic.
 */
Route::get('whatsapp/webhook', [WhatsappWebhookController::class, 'verify'])
    ->middleware('throttle:60,1,whatsapp-webhook-verify')
    ->name('whatsapp.webhook.verify');
Route::post('whatsapp/webhook', [WhatsappWebhookController::class, 'receive'])
    ->middleware('throttle:1200,1,whatsapp-webhook')
    ->name('whatsapp.webhook');
