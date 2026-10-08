<?php

use App\Http\Controllers\Webhooks\SlackWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Slack Events API webhook. The {secret} is the per-app
 * `webhook_secret` (routes the event to the right agent); the controller
 * additionally verifies Slack's HMAC request signature against the app's
 * signing secret. No session / CSRF (bare route).
 *
 * Throttled per source IP with a high cap: the provider sends every tenant's
 * events from a small pool of IPs, so the limit only stops a flood from one
 * non-provider address and must never slow real traffic.
 */
Route::post('slack/webhook/{secret}', SlackWebhookController::class)
    ->middleware('throttle:1200,1,slack-webhook')
    ->name('slack.webhook');
