<?php

use App\Http\Controllers\Webhooks\InstagramWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Instagram Messaging webhook for Chatvia's single Meta app — one
 * app-level Callback URL (no per-account secret). GET is Meta's verify handshake
 * (echo hub.challenge against the app-level verify token), POST carries events
 * verified by the X-Hub-Signature-256 HMAC against the app secret and routed to
 * the agent by IG account id. No session / CSRF (bare routes).
 */
Route::get('instagram/webhook', [InstagramWebhookController::class, 'verify'])->name('instagram.webhook.verify');
Route::post('instagram/webhook', [InstagramWebhookController::class, 'receive'])->name('instagram.webhook');
