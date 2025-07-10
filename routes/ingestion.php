<?php


use App\Http\Controllers\Ingestion\IngestionDocumentController;
use App\Http\Controllers\Ingestion\WebhookIngestionJobController;
use Illuminate\Support\Facades\Route;

Route::prefix('ingestion')->group(function () {
    Route::patch('/webhook', WebhookIngestionJobController::class)
        ->name('ingestion.webhook');

    Route::post('/file', IngestionDocumentController::class)->name('ingestion.file');
});


