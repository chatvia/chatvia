<?php

use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Telegram webhook. The {secret} is the per-bot `webhook_secret` (routes
 * the update to the right agent); the controller additionally verifies the
 * X-Telegram-Bot-Api-Secret-Token header. No session / CSRF (bare route).
 *
 * Throttled per source IP with a high cap: the provider sends every tenant's
 * events from a small pool of IPs, so the limit only stops a flood from one
 * non-provider address and must never slow real traffic.
 */
Route::post('telegram/webhook/{secret}', TelegramWebhookController::class)
    ->middleware('throttle:1200,1,telegram-webhook')
    ->name('telegram.webhook');
