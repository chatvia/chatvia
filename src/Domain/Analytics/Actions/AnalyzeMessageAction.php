<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Services\ToneAnalysis;
use Domain\Billing\Enums\CapabilityEnum;
use Domain\Conversation\Models\Message;

class AnalyzeMessageAction
{
    public function __construct(
        private readonly ToneAnalysis $toneAnalysis,
    ) {}

    public function execute(string $externalMessageId): void
    {
        $message = Message::with('conversation.agent.user')
            ->where('external_id', $externalMessageId)
            ->first();

        if (! $message || $message->role !== 'user') {
            return;
        }

        if ($message->sentiment && $message->tone) {
            return;
        }

        // ToneAnalysis::analyze() returns sentiment + tone in a single LLM
        // call — gate together. Skip when the workspace owner can't see
        // either result so we don't burn inference on data the customer
        // will never view.
        $team = $message->conversation?->agent?->team;
        if (! $team || (
            ! $team->hasCapability(CapabilityEnum::SENTIMENT)
            && ! $team->hasCapability(CapabilityEnum::TONE)
        )) {
            return;
        }

        $analysis = $this->toneAnalysis->analyze($message->content);

        $message->update([
            'sentiment' => $analysis['sentiment']->value,
            'tone' => $analysis['tone']->value,
        ]);
    }
}
