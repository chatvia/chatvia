<?php

namespace Domain\Analytics\Jobs;

use App\Billing\Enums\CapabilityEnum;
use App\TextAnalysis\ToneAnalysis;
use Domain\Chat\Models\ChatMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $externalMessageId,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(ToneAnalysis $toneAnalysis): void
    {
        $message = ChatMessage::with('conversation.agent.user')
            ->where('external_id', $this->externalMessageId)
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
        $owner = $message->conversation?->agent?->user;
        if (! $owner || (
            ! $owner->hasCapability(CapabilityEnum::SENTIMENT)
            && ! $owner->hasCapability(CapabilityEnum::TONE)
        )) {
            return;
        }

        $analysis = $toneAnalysis->analyze($message->content);

        $message->update([
            'sentiment' => $analysis['sentiment']->value,
            'tone' => $analysis['tone']->value,
        ]);
    }
}
