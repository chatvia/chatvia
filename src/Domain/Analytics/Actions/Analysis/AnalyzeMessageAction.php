<?php

namespace Domain\Analytics\Actions\Analysis;

use Domain\Analytics\Data\ConversationAnalysisData;
use Domain\Analytics\Events\MessageAnalyzed;
use Domain\Analytics\Services\ConversationAnalyzer;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Message;

/**
 * Per-message sentiment + tone for a visitor message, in one structured LLM
 * call. Runs only when the agent has sentiment or tone switched on. An
 * unparseable answer saves nothing, so the message keeps no
 * made-up "neutral" values and is not counted in the rollups.
 */
class AnalyzeMessageAction
{
    public function __construct(
        private readonly ConversationAnalyzer $analyzer,
    ) {}

    public function execute(string $externalMessageId): void
    {
        $message = Message::query()
            ->with('conversation.agent')
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

        $analysis = $this->analyzer->analyzeMessage($message->content);

        if (! $analysis instanceof ConversationAnalysisData) {
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
            $agent->workspace_id,
            $wantSentiment ? $analysis->sentiment->value : null,
            $wantTone ? $analysis->tone->value : null,
            $message->created_at,
        );
    }
}
