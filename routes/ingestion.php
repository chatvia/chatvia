<?php

use App\Http\Controllers\Ingestion\IngestionDocumentController;
use App\Http\Controllers\Ingestion\WebhookIngestionJobController;
use Illuminate\Support\Facades\Route;

Route::prefix('ingestion')->middleware('throttle:120,1')->group(function (): void {
    Route::patch('/webhook', WebhookIngestionJobController::class)
        ->name('ingestion.webhook');

    Route::post('/file', IngestionDocumentController::class)->name('ingestion.file');
});
