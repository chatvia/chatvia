<?php

use App\Http\Controllers\Chat\ChatAppearanceController;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\Chat\ChatDeleteAllConversationsController;
use App\Http\Controllers\Chat\ChatHomeController;
use App\Http\Controllers\Chat\ChatMessageDeleteController;
use App\Http\Controllers\Chat\ChatMessageFeedbackController;
use App\Http\Controllers\Chat\ChatModelsListController;
use App\Http\Controllers\Chat\ChatShowLoginController;
use App\Http\Controllers\Chat\ChatStartersController;
use App\Http\Controllers\Chat\ChatStreamController;
use App\Http\Controllers\Chat\Conversations\ConversationCreateController;
use App\Http\Controllers\Chat\Conversations\ConversationDeleteController;
use App\Http\Controllers\Chat\Conversations\ConversationMessagesController;
use App\Http\Controllers\Chat\Conversations\ConversationsController;
use App\Http\Controllers\Chat\Conversations\ConversationUpdate;

Route::prefix('/chat')
    ->middleware(['web'])
    ->group(function () {
        Route::get('/', ChatHomeController::class)->name('chat.home');
        Route::get('/models', ChatModelsListController::class)->name('chat.models.index');
        Route::post('/stream/{conversationId}', ChatStreamController::class)->name('chat.stream');
        Route::post('/conversations/create', ConversationCreateController::class)->name('conversations.create');
        Route::get('/conversations/{conversationId}/messages', ConversationMessagesController::class)->name('conversations.messages');
        Route::get('/conversations', ConversationsController::class)->name('conversations.index');
        Route::patch('/conversations/{conversationId}', ConversationUpdate::class)->name('conversations.update');
        Route::delete('/conversations/{conversationId}', ConversationDeleteController::class)->name('conversations.delete');
        Route::delete('/conversations', ChatDeleteAllConversationsController::class)->name('conversations.delete.all');
        Route::get('login', ChatShowLoginController::class)->name('chat.login');
        Route::get('starters', ChatStartersController::class)->name('chat.starters');
        Route::patch('/messages/feedback', ChatMessageFeedbackController::class)->name('chat.messages.feedback');
        Route::delete('/messages', ChatMessageDeleteController::class)->name('chat.messages.delete');
        Route::get('/appearance', ChatAppearanceController::class)->name('chat.appearance');

        // Should be the last route in the group
        Route::get('/{conversationId}', ChatController::class)->name('chat.show');
    });
