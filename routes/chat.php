<?php

use App\Http\Controllers\Chat\Auth\ChatHandoffController;
use App\Http\Controllers\Chat\Auth\ChatLoginController;
use App\Http\Controllers\Chat\Auth\ChatLogoutController;
use App\Http\Controllers\Chat\Auth\ChatMagicLinkController;
use App\Http\Controllers\Chat\Auth\ChatProfilePhotoDestroyController;
use App\Http\Controllers\Chat\Auth\ChatProfileUpdateController;
use App\Http\Controllers\Chat\Auth\ChatShowLoginController;
use App\Http\Controllers\Chat\Auth\ChatterAgentController;
use App\Http\Controllers\Chat\Conversations\ChatDeleteAllConversationsController;
use App\Http\Controllers\Chat\Conversations\ConversationController;
use App\Http\Controllers\Chat\Conversations\ConversationCreateController;
use App\Http\Controllers\Chat\Conversations\ConversationDeleteController;
use App\Http\Controllers\Chat\Conversations\ConversationMessageController;
use App\Http\Controllers\Chat\Conversations\ConversationSearchController;
use App\Http\Controllers\Chat\Conversations\ConversationUpdate;
use App\Http\Controllers\Chat\Conversations\GenerateConversationTitleController;
use App\Http\Controllers\Chat\Conversations\MessageAttachmentDeleteController;
use App\Http\Controllers\Chat\Embed\ChatEmbedController;
use App\Http\Controllers\Chat\Embed\ChatEmbedHomeController;
use App\Http\Controllers\Chat\Export\ChatExportController;
use App\Http\Controllers\Chat\Forms\SubmitInlineFormController;
use App\Http\Controllers\Chat\Home\ChatAppearanceController;
use App\Http\Controllers\Chat\Home\ChatController;
use App\Http\Controllers\Chat\Home\ChatHomeController;
use App\Http\Controllers\Chat\Home\ChatSourceController;
use App\Http\Controllers\Chat\Home\ChatStarterController;
use App\Http\Controllers\Chat\Messages\AttachmentController;
use App\Http\Controllers\Chat\Messages\AttachmentDeleteController;
use App\Http\Controllers\Chat\Messages\MessageDeleteController;
use App\Http\Controllers\Chat\Messages\MessageFeedbackController;
use App\Http\Controllers\Chat\Messages\MessageRegenerateController;
use App\Http\Controllers\Chat\Stream\ChatStreamController;
use App\Http\Controllers\Chat\Tools\ResolveToolApprovalController;
use App\Http\Controllers\Chat\Transcription\ChatTranscriptionController;
use App\Http\Controllers\Chat\Usage\MessagesUsage;

// Moves a dashboard sign-in (the end of a chat sign-in link's two-factor
// challenge) into an agent's chat session. Outside /chat, so it runs on the
// dashboard session; named apart from `chat.*` so it is never framable.
Route::get('chat-handoff/{agentSlug}', ChatHandoffController::class)
    ->middleware('auth')
    ->name('chat-handoff');

/*
 * The hosted chat: every page and call of an agent's chat lives under
 * /chat/{agentSlug} (the agent's slug, or its external id for the permanent
 * and embed addresses). `chat.agent` resolves the agent first; the chat runs on
 * its own session and `chat` guard (UseHostedChatSession), apart from the
 * dashboard.
 */
Route::prefix('chat/{agentSlug}')
    ->middleware('chat.agent')
    ->group(function (): void {
        Route::get('appearance', ChatAppearanceController::class)->name('chat.appearance');

        Route::middleware('guest')->group(function (): void {
            Route::get('login', ChatShowLoginController::class)->name('chat.login.show');
            Route::post('login', ChatLoginController::class)
                ->middleware('throttle:5,1')
                ->name('chat.login');

            Route::post('magic-link', ChatMagicLinkController::class)
                ->middleware('throttle:6,1')
                ->name('chat.magic-link.send');
        });

        Route::middleware('chat')->group(function (): void {
            Route::get('/', ChatHomeController::class)->name('chat.home');

            Route::get('embed', ChatEmbedHomeController::class)->name('chat.embed.home');
            Route::get('embed/c/{conversationId}', ChatEmbedController::class)->name('chat.embed.show');

            Route::get('agents', ChatterAgentController::class)->name('chat.agents.index');

            Route::post('stream/{conversationId}', ChatStreamController::class)->middleware('throttle:chat-stream')->name('chat.stream');

            // Resume a paused agent turn after the user approves/denies a gated
            // tool call. {approval} binds ToolApprovalCheckpoint by external_id.
            Route::post('tools/{approval}/resolve', ResolveToolApprovalController::class)->name('chat.tools.resolve');

            // Inline-form submit. {formInstance} binds FormSubmission by external_id.
            // Stores a Lead and/or FormSubmission — never opens a new (billed) turn.
            Route::post('forms/{formInstance}/submit', SubmitInlineFormController::class)
                ->middleware('throttle:chat-stream')->name('chat.forms.submit');

            Route::post('conversations/create', ConversationCreateController::class)->name('conversations.create');
            Route::get('conversations/{conversationId}/messages', ConversationMessageController::class)->name('conversations.messages');
            Route::post('conversations/{conversationId}/title', GenerateConversationTitleController::class)->name('conversations.title');
            Route::get('conversations', ConversationController::class)->name('conversations.index');
            Route::get('conversations/search', ConversationSearchController::class)->name('conversations.search');
            Route::patch('conversations/{conversationId}', ConversationUpdate::class)->name('conversations.update');
            Route::delete('conversations/{conversationId}', ConversationDeleteController::class)->name('conversations.delete');
            Route::delete('conversations/{conversationId}/attachments/{attachment}', MessageAttachmentDeleteController::class)
                ->name('chat.conversations.attachments.delete');
            Route::delete('conversations', ChatDeleteAllConversationsController::class)->name('conversations.delete.all');

            // Reachable by both logged-in members and link-signed-in visitors;
            // the controller invalidates the chat session (clearing the sign-in grant).
            Route::post('logout', ChatLogoutController::class)->name('chat.logout');

            Route::get('starters', ChatStarterController::class)->name('chat.starters');
            Route::patch('messages/feedback', MessageFeedbackController::class)->name('chat.messages.feedback');
            Route::delete('messages', MessageDeleteController::class)->name('chat.messages.delete');
            Route::post('messages/regenerate', MessageRegenerateController::class)->name('chat.messages.regenerate');
            Route::post('messages/attachments', AttachmentController::class)->middleware('throttle:chat-stream')->name('chat.messages.attachments.store');
            Route::delete('messages/attachments/{attachment}', AttachmentDeleteController::class)->name('chat.messages.attachments.delete');

            Route::get('conversations/{conversationId}/export', ChatExportController::class)->name('conversations.export');
            Route::get('conversations/{conversationId}/sources', ChatSourceController::class)->name('chat.sources');

            Route::get('c/{conversationId}', ChatController::class)->name('chat.show');

            Route::patch('profile', ChatProfileUpdateController::class)->name('chat.profile.update');
            Route::delete('profile/photo', ChatProfilePhotoDestroyController::class)->name('chat.profile.photo.destroy');

            Route::get('usage', MessagesUsage::class)->name('chat.usage');

            Route::post('transcribe', ChatTranscriptionController::class)->middleware('throttle:chat-stream')->name('chat.transcribe');
        });
    });
