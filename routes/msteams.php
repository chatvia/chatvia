<?php

use App\Http\Controllers\Webhooks\TeamsWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Microsoft Teams (Bot Framework) messaging endpoint. The {secret} is
 * the per-bot `webhook_secret` (routes the activity to the right agent); the
 * controller additionally verifies the connector's Bearer JWT against
 * Microsoft's published signing keys. No session / CSRF (bare route).
 */
Route::post('msteams/webhook/{secret}', TeamsWebhookController::class)->name('msteams.webhook');
