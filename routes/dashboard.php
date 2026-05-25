<?php

use App\Http\Controllers\CapabilitiesController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DashboardStatsController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\Tickets\TicketsController;
use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('my/stats', DashboardStatsController::class)->name('dashboard.stats');
    Route::get('my/features', FeaturesController::class)->name('dashboard.features');

    Route::get('my/knowledge-base', KnowledgeFilesShowController::class)->name('knowledge-base.show');
    Route::get('my/knowledge-base/list', KnowledgeFilesListController::class)->name('knowledge-base.list');
    Route::get('my/knowledge-base/download/{agentFile}', KnowledgeFilesDownload::class)->name('knowledge-base.download');

    Route::get('my/usage', UsageController::class)->name('usage');

    Route::get('my/support', SupportController::class)->name('support');
    Route::get('my/tickets', [TicketsController::class, 'index'])->name('tickets.index');
    Route::post('my/tickets', [TicketsController::class, 'store'])->name('tickets.store');
    Route::get('my/tickets/{ticket}', [TicketsController::class, 'show'])->name('tickets.show');
    Route::post('my/tickets/{ticket}/reply', [TicketsController::class, 'reply'])->name('tickets.reply.store');
    Route::post('my/tickets/{ticket}/priority', [TicketsController::class, 'changePriority'])->name('tickets.priority');

    Route::get('my/capabilities', CapabilitiesController::class)->name('my.capabilities');
});
