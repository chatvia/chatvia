<?php

use App\Http\Controllers\WebhookChunkController;

Route::post('/chunk', WebhookChunkController::class)->name('webhook.chunk');
