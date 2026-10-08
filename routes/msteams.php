<?php

use App\Http\Controllers\Webhooks\TeamsWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Microsoft Teams (Bot Framework) messaging endpoint. The {secret} is
 * the per-bot `webhook_secret` (routes the activity to the right agent); the
 * controller additionally verifies the connector's Bearer JWT against
 * Microsoft's published signing keys. No session / CSRF (bare route).
 *
 * Throttled per source IP with a high cap: the provider sends every tenant's
 * events from a small pool of IPs, so the limit only stops a flood from one
 * non-provider address and must never slow real traffic.
 */
Route::post('msteams/webhook/{secret}', TeamsWebhookController::class)
    ->middleware('throttle:1200,1,msteams-webhook')
    ->name('msteams.webhook');
