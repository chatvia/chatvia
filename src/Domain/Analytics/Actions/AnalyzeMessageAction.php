<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Data\ConversationAnalysisData;
use Domain\Analytics\Events\MessageAnalyzed;
use Domain\Analytics\Services\ConversationAnalyzer;
use Domain\Billing\Enums\CapabilityEnum;
use Domain\Conversation\Models\Message;
use Domain\Llm\Usage\TokenUsageContext;

class AnalyzeMessageAction
{
    public function __construct(
        private readonly ConversationAnalyzer $analyzer,
        private readonly TokenUsageContext $usage,
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

        // analyzeMessage() returns sentiment + tone in a single structured-output
        // LLM call. A dimension is "wanted" only when the plan exposes it AND
        // the agent hasn't switched it off (Setup → Settings). Skip the call
        // entirely when neither is wanted.
        $agent = $message->conversation?->agent;
        $team = $agent?->team;

        if (! $agent || ! $team) {
            return;
        }

        $wantSentiment = $team->hasCapability(CapabilityEnum::SENTIMENT) && $agent->analyticsEnabled('sentiment');
        $wantTone = $team->hasCapability(CapabilityEnum::TONE) && $agent->analyticsEnabled('tone');

        if (! $wantSentiment && ! $wantTone) {
            return;
        }

        // Attribute the tone/sentiment LLM call's tokens to the workspace (analytics).
        $analysis = $this->usage->track(
            $agent->user_id,
            fn (): ConversationAnalysisData => $this->analyzer->analyzeMessage($message->content),
            'analysis',
        );

        // Persist both columns regardless (the single call already paid for
        // both) so the `$message->sentiment && $message->tone` guard above
        // stays a clean idempotency check. Only the *wanted* dimensions roll
        // up to the daily analytics tables — that is what the toggle controls.
        $message->update([
            'sentiment' => $analysis->sentiment->value,
            'tone' => $analysis->tone->value,
        ]);

        MessageAnalyzed::dispatch(
            $agent->id,
            $agent->user_id,
            $wantSentiment ? $analysis->sentiment->value : null,
            $wantTone ? $analysis->tone->value : null,
            $message->created_at,
        );
    }
}
