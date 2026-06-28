<?php

use App\Http\Controllers\Analytics\AnalyticsChatController;
use App\Http\Controllers\Analytics\AnalyticsChatStreamController;
use App\Http\Controllers\Billing\Capabilities\CapabilityController;
use App\Http\Controllers\Billing\Usage\UsageController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DashboardStatsController;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController;
use App\Http\Controllers\Onboarding\CompleteOnboardingController;
use App\Http\Controllers\Support\SupportController;
use App\Http\Controllers\Tickets\ChangeTicketPriorityController;
use App\Http\Controllers\Tickets\ReplyToTicketController;
use App\Http\Controllers\Tickets\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can.access.dashboard'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('my/stats', DashboardStatsController::class)->name('dashboard.stats');

    // Marks the signed-in user as onboarded once they finish or skip the
    // first-run welcome tour shown on the dashboard.
    Route::post('my/onboarding/complete', CompleteOnboardingController::class)->name('onboarding.complete');

    // Owner-facing "Ask your data" analytics chat — ephemeral (stores nothing).
    Route::get('my/ask', AnalyticsChatController::class)->name('analytics.chat');
    Route::post('my/ask/stream', AnalyticsChatStreamController::class)
        ->middleware('throttle:30,1')
        ->name('analytics.chat.stream');

    Route::get('my/knowledge-base', KnowledgeFilesShowController::class)->name('knowledge-base.show');
    Route::get('my/knowledge-base/list', KnowledgeFilesListController::class)->name('knowledge-base.list');
    Route::get('my/knowledge-base/download/{agentFile}', KnowledgeFilesDownload::class)->name('knowledge-base.download');

    Route::get('my/usage', UsageController::class)->name('usage');

    Route::get('my/support', SupportController::class)->name('support');
    Route::get('my/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('my/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('my/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('my/tickets/{ticket}/reply', ReplyToTicketController::class)->name('tickets.reply.store');
    Route::post('my/tickets/{ticket}/priority', ChangeTicketPriorityController::class)->name('tickets.priority');

    Route::get('my/capabilities', CapabilityController::class)->name('my.capabilities');
});
