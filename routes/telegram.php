<?php

use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Telegram webhook. The {secret} is the per-bot `webhook_secret` (routes
 * the update to the right agent); the controller additionally verifies the
 * X-Telegram-Bot-Api-Secret-Token header. No session / CSRF (bare route).
 */
Route::post('telegram/webhook/{secret}', TelegramWebhookController::class)->name('telegram.webhook');
