<?php

use App\Http\Controllers\PipelineStagesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Pipeline Stages Management (Global - affects all agents)
    Route::get('pipeline-stages', [PipelineStagesController::class, 'index'])->name('pipeline-stages.index');
    Route::post('pipeline-stages', [PipelineStagesController::class, 'store'])->name('pipeline-stages.store');
    Route::patch('pipeline-stages/{stage}', [PipelineStagesController::class, 'update'])->name('pipeline-stages.update');
    Route::patch('pipeline-stages/{stage}/toggle', [PipelineStagesController::class, 'toggleActive'])->name('pipeline-stages.toggle');
    Route::patch('pipeline-stages/reorder', [PipelineStagesController::class, 'updateOrder'])->name('pipeline-stages.reorder');
    Route::delete('pipeline-stages/{stage}', [PipelineStagesController::class, 'destroy'])->name('pipeline-stages.destroy');
});
