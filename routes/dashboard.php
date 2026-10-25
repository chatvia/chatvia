<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\AnalyticsChatController;
use App\Http\Controllers\Analytics\AnalyticsChatStreamController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DashboardStatsController;
use App\Http\Controllers\Inbox\InboxController;
use App\Http\Controllers\Inbox\InboxConversationController;
use App\Http\Controllers\Inbox\InboxWaitingCountController;
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

    // The Inbox: who is chatting with the workspace's agents right now, across
    // every agent the person may view. Replies go through the per-agent
    // handoff routes, so their permission and tenancy checks still apply.
    Route::get('my/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('my/inbox/conversations', [InboxConversationController::class, 'index'])->name('inbox.conversations');
    Route::get('my/inbox/waiting-count', InboxWaitingCountController::class)->name('inbox.waiting-count');

    // The notification center: the full page, the bell's feed, marking
    // notifications read and clearing them. Each person only ever reaches
    // their own.
    Route::get('my/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('my/notifications/feed', NotificationFeedController::class)->name('notifications.feed');
    Route::post('my/notifications/read-all', ReadAllNotificationsController::class)->name('notifications.read-all');
    Route::delete('my/notifications', ClearNotificationsController::class)->name('notifications.clear');
    Route::patch('my/notifications/{notification}', [NotificationController::class, 'update'])->name('notifications.update');
});
