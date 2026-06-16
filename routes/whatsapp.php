<?php

use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public WhatsApp Cloud API webhook for Chatvia's single Meta app — one
 * app-level Callback URL (no per-number secret). GET is Meta's verify handshake
 * (echo hub.challenge against the app-level verify token), POST carries events
 * verified by the X-Hub-Signature-256 HMAC against the app secret and routed to
 * the agent by the phone number id. No session / CSRF (bare routes).
 */
Route::get('whatsapp/webhook', [WhatsappWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('whatsapp/webhook', [WhatsappWebhookController::class, 'receive'])->name('whatsapp.webhook');
