<?php

use App\Http\Controllers\Chat\Auth\ChatLoginController;
use App\Http\Controllers\Chat\Auth\ChatLogoutController;
use App\Http\Controllers\Chat\Auth\ChatMemberAgentsController;
use App\Http\Controllers\Chat\Auth\ChatMemberSwitchAgentController;
use App\Http\Controllers\Chat\Auth\ChatProfileUpdateController;
use App\Http\Controllers\Chat\Auth\ChatShowLoginController;
use App\Http\Controllers\Chat\ChatAppearanceController;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\Chat\ChatDeleteAllConversationsController;
use App\Http\Controllers\Chat\ChatEmbedController;
use App\Http\Controllers\Chat\ChatEmbedHomeController;
use App\Http\Controllers\Chat\ChatHomeController;
use App\Http\Controllers\Chat\ChatMessageDeleteController;
use App\Http\Controllers\Chat\ChatMessageFeedbackController;
use App\Http\Controllers\Chat\ChatMessageRegenerateController;
use App\Http\Controllers\Chat\ChatMessagesUsage;
use App\Http\Controllers\Chat\ChatModelsListController;
use App\Http\Controllers\Chat\ChatSourcesController;
use App\Http\Controllers\Chat\ChatStartersController;
use App\Http\Controllers\Chat\ChatStreamController;
use App\Http\Controllers\Chat\ChatTranscriptionController;
use App\Http\Controllers\Chat\Conversations\ConversationCreateController;
use App\Http\Controllers\Chat\Conversations\ConversationDeleteController;
use App\Http\Controllers\Chat\Conversations\ConversationMessagesController;
use App\Http\Controllers\Chat\Conversations\ConversationsController;
use App\Http\Controllers\Chat\Conversations\ConversationUpdate;
use App\Http\Controllers\Chat\Export\ChatExportController;

Route::get('/chat/appearance', ChatAppearanceController::class)->name('chat.appearance');

Route::middleware('guest.member')->group(function () {
    Route::get('chat/login', ChatShowLoginController::class)->name('chat.login.show');
    Route::post('chat/login', ChatLoginController::class)->name('chat.login');
});

Route::prefix('/chat')
    ->middleware(['chat'])
    ->group(function () {
        Route::get('/', ChatHomeController::class)->name('chat.home');

        Route::get('embed', ChatEmbedHomeController::class)->name('chat.embed.home');
        Route::get('embed/c/{conversationId}', ChatEmbedController::class)->name('chat.embed.show');

        Route::get('/models', ChatModelsListController::class)->name('chat.models.index');
        Route::get('/agents', ChatMemberAgentsController::class)->name('chat.member.agents');

        Route::post('/agents/switch/{agent}', ChatMemberSwitchAgentController::class)->name('chat.member.agents.switch');

        Route::post('/stream/{conversationId}', ChatStreamController::class)->name('chat.stream');

        Route::post('/conversations/create', ConversationCreateController::class)->name('conversations.create');
        Route::get('/conversations/{conversationId}/messages', ConversationMessagesController::class)->name('conversations.messages');
        Route::get('/conversations', ConversationsController::class)->name('conversations.index');
        Route::patch('/conversations/{conversationId}', ConversationUpdate::class)->name('conversations.update');
        Route::delete('/conversations/{conversationId}', ConversationDeleteController::class)->name('conversations.delete');
        Route::delete('/conversations', ChatDeleteAllConversationsController::class)->name('conversations.delete.all');

        Route::middleware('auth.member')->group(function () {
            Route::post('logout', ChatLogoutController::class)->name('chat.logout');
        });

        Route::get('starters', ChatStartersController::class)->name('chat.starters');
        Route::patch('/messages/feedback', ChatMessageFeedbackController::class)->name('chat.messages.feedback');
        Route::delete('/messages', ChatMessageDeleteController::class)->name('chat.messages.delete');
        Route::post('/messages/regenerate', ChatMessageRegenerateController::class)->name('chat.messages.regenerate');

        Route::get('/conversations/{conversationId}/export', ChatExportController::class)->name('conversations.export');
        Route::get('/conversations/{conversationId}/sources', ChatSourcesController::class)->name('chat.sources');

        // Should be the last route in the group
        Route::get('/c/{conversationId}', ChatController::class)->name('chat.show');

        Route::patch('/profile', ChatProfileUpdateController::class)->name('chat.profile.update');

        Route::get('/usage', ChatMessagesUsage::class)->name('chat.usage');

        Route::post('/transcribe', ChatTranscriptionController::class)->name('chat.transcribe');
    });
