<?php

namespace Domain\Channel\Telegram\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Data\StreamRequestMessageData;
use Domain\Conversation\Services\Streaming\Sources\GuardBlockSource;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sources\QASource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs one Telegram turn through the REAL chat pipeline (RAG + tools + guardrails
 * + persistence + billing) but collects the **full reply as a string** instead of
 * streaming it — Telegram is request/response, not SSE.
 *
 * Mirrors {@see ChatStreamService}: same owner
 * subscription/credit gate, same source selection (guard-block → Q&A → LLM). The
 * sources persist + bill + apply output guards as the generator is drained; the
 * final (already redacted/corrected) text lands in `ChatContext::streamedMessage`.
 */
class TelegramReplyRunner
{
    /** Re-send the typing indicator at most this often (Telegram clears it after ~5s). */
    private const TYPING_REFRESH_SECONDS = 4.0;

    public function __construct(
        private readonly ChatService $chatService,
        private readonly LlmSource $llmSource,
        private readonly QASource $qaSource,
        private readonly GuardBlockSource $guardBlockSource,
        private readonly UserCreditSystem $creditSystem,
        private readonly TelegramClient $client,
    ) {}

    public function reply(TelegramChat $chat, string $message): string
    {
        $agent = $chat->bot->agent;
        $agent->loadMissing(['agentPrompt', 'llm']);
        $owner = $agent->user;

        if ($owner === null || ! $this->ownerCanReceive($owner)) {
            return 'Sorry — this assistant is unavailable right now. Please try again later.';
        }

        if ($agent->llm === null) {
            return 'Sorry — this assistant is not fully configured yet.';
        }

        $data = new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(model: $agent->llm, message: $message),
            externalSessionId: $chat->session_external_id,
            conversationId: $chat->conversation_external_id,
            source: 'telegram',
            saveUserMessageImmediately: true,
            requestId: 'tg_'.Str::uuid(),
        );

        try {
            $context = $this->chatService->initializeChatContext($data);
            $context = $this->chatService->enrichContextWithKnowledge($context);

            $events = match (true) {
                $context->guardBlocked => $this->guardBlockSource->events($context),
                $context->hasQAMatch() => $this->qaSource->events($context),
                default => $this->llmSource->events($context, $this->chatService->prepareLlmMessages($context)),
            };

            // Drain the generator: the source streams nowhere here, but its
            // side effects (persist, bill, output-guard correction) all run.
            // While draining, keep the "typing…" indicator alive so a slow
            // generation doesn't look frozen on the user's side.
            $token = $chat->bot->bot_token;
            $lastTyping = microtime(true);

            foreach ($events as $_event) {
                if (microtime(true) - $lastTyping >= self::TYPING_REFRESH_SECONDS) {
                    $this->client->sendChatAction($token, $chat->chat_id);
                    $lastTyping = microtime(true);
                }
            }

            $reply = trim($context->streamedMessage);

            return $reply !== '' ? $reply : 'Sorry — I could not produce a reply.';
        } catch (Throwable $e) {
            Log::error('Telegram reply failed', [
                'error' => $e->getMessage(),
                'agent_id' => $agent->id,
                'chat_id' => $chat->chat_id,
            ]);

            return 'Sorry — something went wrong. Please try again.';
        }
    }

    /**
     * Same gate the web chat applies to the workspace owner: an active
     * subscription/trial AND remaining message credits.
     */
    private function ownerCanReceive(User $owner): bool
    {
        if ($owner->isAdmin()) {
            return true;
        }

        $billing = $owner->billing;
        $subscribed = (bool) ($billing?->subscribed() || $billing?->onTrial());

        return $subscribed && $this->creditSystem->setUserId($owner->id)->canSendMessages();
    }
}
