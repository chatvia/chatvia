<?php

use App\Http\Controllers\Webhooks\SlackWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Public Slack Events API webhook. The {secret} is the per-app
 * `webhook_secret` (routes the event to the right agent); the controller
 * additionally verifies Slack's HMAC request signature against the app's
 * signing secret. No session / CSRF (bare route).
 */
Route::post('slack/webhook/{secret}', SlackWebhookController::class)->name('slack.webhook');
