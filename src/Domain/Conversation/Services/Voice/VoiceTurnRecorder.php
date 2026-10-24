<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Conversation\Actions\Messages\AddMessageToConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Illuminate\Support\Str;

/**
 * Saves a voice call's turns as a typed turn's are saved: the visitor's
 * words and the agent's reply become messages of the conversation (live
 * memory now, the chat log through the queue), the reply counts against the
 * workspace's message limit, and the guardrails read both.
 *
 * Speech reaches the model before any guard can read it, so the guards here
 * work on what was written down: the visitor's words are masked before they
 * are stored and may refuse the turn; the reply is masked or replaced before
 * it is stored.
 */
final readonly class VoiceTurnRecorder
{
    public function __construct(
        private VoiceTurnFactory $factory,
        private AddMessageToConversationAction $addMessage,
        private AssistantReplyRecorder $replies,
        private GuardrailService $guardrails,
        private GuardrailRecorder $guardrailRecorder,
    ) {}

    /**
     * Store what the visitor said, after the input guardrails. Speech that
     * could not be written down is stored as a placeholder, unguarded.
     */
    public function visitorSaid(VoiceTurn $turn, string $transcript): GuardResultData
    {
        $context = $turn->context;
        $request = $context->requestData;
        $text = Str::trim($transcript);

        $guard = blank($text)
            ? GuardResultData::clean(__('(Voice message that could not be transcribed)'))
            : $this->guardrails->inspectInput($request->agent, $text);

        $this->addMessage->execute(
            new ChatScopeData($request->agent, $request->externalSessionId, $request->conversationId),
            $this->factory->userTurn($request, $context->userMessageId ?? '', $guard->text),
        );
        // The masked text is recorded, never the raw words.
        $this->guardrailRecorder->record($context, $guard->text, $guard);

        $turn->userStored = true;
        $turn->words = blank($text) ? '' : $guard->text;

        return $guard;
    }

    /** Store the agent's spoken reply; it counts against the message limit. */
    public function reply(VoiceTurn $turn): void
    {
        $context = $turn->context;
        $context->resetStreamedMessage();
        $context->appendStreamedMessage($turn->spoken);

        $guard = $this->guardrails->inspectOutput($context->requestData->agent, $turn->spoken, $context->queryLanguage);

        if ($guard->triggered()) {
            $context->resetStreamedMessage();
            $context->appendStreamedMessage($guard->text);
            $this->guardrailRecorder->record($context, $guard->text, $guard);
        }

        $this->replies->recordCompletedReply($context);
        $turn->replyStored = true;
    }

    /** Store the refusal a guardrail gave instead of a reply; it is not counted. */
    public function refusal(VoiceTurn $turn, string $text): void
    {
        $context = $turn->context;
        $context->resetStreamedMessage();
        $context->appendStreamedMessage($text);

        $this->replies->recordRefusal($context);
        $turn->replyStored = true;
    }
}
