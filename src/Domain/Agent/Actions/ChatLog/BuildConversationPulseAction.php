<?php

namespace Domain\Agent\Actions\ChatLog;

use Domain\Agent\Data\Resources\ChatLog\Pulse\ChatLogPulseConversationData;
use Domain\Agent\Data\Resources\ChatLog\Pulse\ChatLogPulseMessageData;
use Domain\Agent\Data\Resources\ChatLog\Pulse\ChatLogPulsePeakData;
use Domain\Agent\Data\Resources\ChatLog\Pulse\ChatLogPulseResourceData;
use Domain\Agent\Data\Resources\ChatLog\Pulse\ChatLogPulseSnapshotData;
use Domain\Agent\Data\Resources\ChatLog\Pulse\ChatLogPulseSummaryData;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;

/**
 * Build the per-conversation chat-log "pulse" — a tone/sentiment timeline of
 * the visitor's messages plus a high-level summary (start/end mood, peak
 * negative/positive, average, trend, and whether the conversation resolved).
 *
 * Lives in the domain so the controller stays a traffic cop: authz + delegate.
 */
class BuildConversationPulseAction
{
    /** Characters of each visitor message the timeline shows. */
    private const int PREVIEW_LENGTH = 50;

    public function execute(Agent $agent, Conversation $conversation): ChatLogPulseResourceData
    {
        // Only the visitor's messages, and only the start of each text: the
        // timeline shows a short preview. Reading twice its length leaves
        // Str::limit (which counts display width) room to see whether the
        // text needs cutting.
        $userMessages = $conversation->messages()
            ->where('role', MessageRole::User)
            ->orderBy('created_at')
            ->orderBy('id')
            ->select(['tone', 'sentiment', 'created_at'])
            ->selectRaw('left(content, ?) as content', [self::PREVIEW_LENGTH * 2])
            ->get();

        $points = $userMessages
            ->map(fn (Message $message, int $index): ChatLogPulseMessageData => $this->point($message, $index));

        return new ChatLogPulseResourceData(
            messages: new DataCollection(ChatLogPulseMessageData::class, $points),
            summary: $this->summary($points),
            conversation: new ChatLogPulseConversationData(
                id: $conversation->external_id,
                sentiment: $conversation->sentiment,
                tone: $conversation->tone,
                total_messages: $conversation->messages()->count(),
                user_messages: $userMessages->count(),
            ),
        );
    }

    private function point(Message $message, int $index): ChatLogPulseMessageData
    {
        $toneScore = $this->toneScore($message->tone);
        $sentimentScore = $this->sentimentScore($message->sentiment);

        return new ChatLogPulseMessageData(
            index: $index + 1,
            content_preview: str($message->content)->limit(self::PREVIEW_LENGTH)->toString(),
            tone: $message->tone,
            sentiment: $message->sentiment,
            // Prefer the tone signal when the LLM tagged tone; fall back to sentiment.
            sentiment_score: $message->tone ? $toneScore : $sentimentScore,
            tone_score: $toneScore,
            created_at: $message->created_at->toISOString(),
        );
    }

    /**
     * @param  Collection<int, ChatLogPulseMessageData>  $points
     */
    private function summary(Collection $points): ChatLogPulseSummaryData
    {
        if ($points->isEmpty()) {
            return new ChatLogPulseSummaryData(
                started_as: null,
                ended_as: null,
                peak_negative: null,
                peak_positive: null,
                avg_sentiment: 0,
                trend: 'stable',
                resolution: false,
            );
        }

        $first = $points->first();
        $last = $points->last();
        /** @var ChatLogPulseMessageData $low */
        $low = $points->sortBy('sentiment_score')->first();
        /** @var ChatLogPulseMessageData $high */
        $high = $points->sortByDesc('sentiment_score')->first();

        $trend = 'stable';
        if ($last->sentiment_score > $first->sentiment_score + 0.5) {
            $trend = 'improving';
        } elseif ($last->sentiment_score < $first->sentiment_score - 0.5) {
            $trend = 'declining';
        }

        return new ChatLogPulseSummaryData(
            started_as: new ChatLogPulseSnapshotData(tone: $first->tone, sentiment: $first->sentiment),
            ended_as: new ChatLogPulseSnapshotData(tone: $last->tone, sentiment: $last->sentiment),
            peak_negative: $low->sentiment_score < 0
                ? new ChatLogPulsePeakData(index: $low->index, tone: $low->tone, sentiment: $low->sentiment)
                : null,
            peak_positive: $high->sentiment_score > 0
                ? new ChatLogPulsePeakData(index: $high->index, tone: $high->tone, sentiment: $high->sentiment)
                : null,
            avg_sentiment: round((float) $points->avg('sentiment_score'), 2),
            trend: $trend,
            resolution: $first->sentiment_score < 0 && $last->sentiment_score >= 0,
        );
    }

    private function sentimentScore(?Sentiment $sentiment): float
    {
        return match ($sentiment) {
            Sentiment::Positive => 1,
            Sentiment::Negative => -1,
            Sentiment::Neutral, null => 0,
        };
    }

    private function toneScore(?Tone $tone): float
    {
        return match ($tone) {
            Tone::Satisfied => 1,
            Tone::Confused => -0.25,
            Tone::Urgent => -0.5,
            Tone::Frustrated => -1,
            Tone::Neutral, null => 0,
        };
    }
}
