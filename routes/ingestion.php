<?php

use App\Http\Controllers\Ingestion\IngestionDocumentController;
use App\Http\Controllers\Ingestion\WebhookIngestionJobController;
use App\Http\Middleware\Auth\VerifyIngestionSignature;
use Illuminate\Support\Facades\Route;

// Server-to-server callbacks from the ingestion pipeline. The signature check
// runs before each controller (and its FormRequest validation).
Route::prefix('ingestion')->middleware(['throttle:120,1', VerifyIngestionSignature::class])->group(function (): void {
    Route::patch('/webhook', WebhookIngestionJobController::class)
        ->name('ingestion.webhook');

    Route::post('/file', IngestionDocumentController::class)->name('ingestion.file');
});
