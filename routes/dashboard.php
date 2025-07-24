<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DashboardStatsController;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController;
use App\Http\Controllers\Members\MembersControllerShow;
use App\Http\Controllers\Members\MembersListController;
use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('stats', DashboardStatsController::class)->name('dashboard.stats');

    Route::get('/knowledge-base', KnowledgeFilesShowController::class)->name('knowledge-base.show');
    Route::get('/knowledge-base/list', KnowledgeFilesListController::class)->name('knowledge-base.list');
    Route::get('/knowledge-base/download/{agentFile}', KnowledgeFilesDownload::class)->name('knowledge-base.download');

    Route::get('/members', MembersControllerShow::class)->name('members.show');
    Route::get('/members/list', MembersListController::class)->name('members.list');

    Route::get('usage', UsageController::class)->name('usage');
});
