<?php

use App\Http\Controllers\CapabilitiesController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DashboardStatsController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController;
use App\Http\Controllers\Members\MembersControllerShow;
use App\Http\Controllers\Members\MembersListController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Tickets\TicketChangePriorityController;
use App\Http\Controllers\Tickets\TicketReplyController;
use App\Http\Controllers\Tickets\TicketsController;
use App\Http\Controllers\Tickets\TicketShowController;
use App\Http\Controllers\Tickets\TicketStoreController;
use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('dashboard/stats', DashboardStatsController::class)->name('dashboard.stats');
    Route::get('dashboard/features', FeaturesController::class)->name('dashboard.features');

    Route::get('dashboard/knowledge-base', KnowledgeFilesShowController::class)->name('knowledge-base.show');
    Route::get('dashboard/knowledge-base/list', KnowledgeFilesListController::class)->name('knowledge-base.list');
    Route::get('dashboard/knowledge-base/download/{agentFile}', KnowledgeFilesDownload::class)->name('knowledge-base.download');

    Route::get('dashboard/members', MembersControllerShow::class)->name('members.show');
    Route::get('dashboard/members/list', MembersListController::class)->name('members.list');

    Route::get('my/usage', UsageController::class)->name('usage');

    Route::get('dashboard/support', SupportController::class)->name('support');
    Route::get('dashboard/tickets', TicketsController::class)->name('tickets');
    Route::post('dashboard/tickets', TicketStoreController::class)->name('tickets.store');
    Route::get('dashboard/tickets/{ticket}', TicketShowController::class)->name('tickets.show');
    Route::post('dashboard/tickets/{ticket}/reply', TicketReplyController::class)->name('tickets.reply.store');
    Route::post('dashboard/tickets/{ticket}/priority', TicketChangePriorityController::class)->name('tickets.priority');

    Route::get('my/capabilities', CapabilitiesController::class)->name('my.capabilities');
    Route::get('my/teams', [TeamController::class, 'index'])->name('my.teams');
});
