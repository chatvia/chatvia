<?php

namespace Domain\Analytics\Actions\Analysis;

use Domain\Analytics\Data\ConversationAnalysisData;
use Domain\Analytics\Events\MessageAnalyzed;
use Domain\Analytics\Services\ConversationAnalyzer;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Message;
use Domain\Llm\Usage\TokenUsageContext;

/**
 * Per-message sentiment + tone for a visitor message, in one structured LLM
 * call. Runs only when the plan exposes a dimension and the agent has it
 * switched on. An unparseable answer saves nothing, so the message keeps no
 * made-up "neutral" values and is not counted in the rollups.
 */
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

        if (! $message || $message->role !== MessageRole::User) {
            return;
        }

        if ($message->sentiment && $message->tone) {
            return;
        }

        // analyzeMessage() returns sentiment + tone in a single structured-output
        // LLM call. A dimension is "wanted" unless the agent switched it off
        // (Setup → Settings). Skip the call entirely when neither is wanted.
        $agent = $message->conversation?->agent;

        if (! $agent) {
            return;
        }

        $wantSentiment = $agent->analyticsEnabled('sentiment');
        $wantTone = $agent->analyticsEnabled('tone');

        if (! $wantSentiment && ! $wantTone) {
            return;
        }

        // Attribute the tone/sentiment LLM call to the workspace in Spectra.
        $analysis = $this->usage->track(
            $agent->user_id,
            fn (): ?ConversationAnalysisData => $this->analyzer->analyzeMessage($message->content),
        );

        if ($analysis === null) {
            return;
        }

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
