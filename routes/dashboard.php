<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\AnalyticsChatController;
use App\Http\Controllers\Analytics\AnalyticsChatStreamController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DashboardStatsController;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload;
use App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController;
use App\Http\Controllers\Notification\ClearNotificationsController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationFeedController;
use App\Http\Controllers\Notification\ReadAllNotificationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can.access.dashboard', 'workspace.scope'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('my/stats', DashboardStatsController::class)->name('dashboard.stats');

    // Owner-facing "Ask your data" analytics chat — ephemeral (stores nothing).
    Route::get('my/ask', AnalyticsChatController::class)->name('analytics.chat');
    Route::post('my/ask/stream', AnalyticsChatStreamController::class)
        ->middleware('throttle:30,1,analytics-chat')
        ->name('analytics.chat.stream');

    Route::get('my/knowledge-base/list', KnowledgeFilesListController::class)->name('knowledge-base.list');
    Route::get('my/knowledge-base/download/{sourceFile}', KnowledgeFilesDownload::class)->name('knowledge-base.download');

    // The notification center: the full page, the bell's feed, marking
    // notifications read and clearing them. Each person only ever reaches
    // their own.
    Route::get('my/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('my/notifications/feed', NotificationFeedController::class)->name('notifications.feed');
    Route::post('my/notifications/read-all', ReadAllNotificationsController::class)->name('notifications.read-all');
    Route::delete('my/notifications', ClearNotificationsController::class)->name('notifications.clear');
    Route::patch('my/notifications/{notification}', [NotificationController::class, 'update'])->name('notifications.update');
});
