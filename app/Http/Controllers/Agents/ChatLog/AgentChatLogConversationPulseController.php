<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Models\Agent;
use App\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentChatLogConversationPulseController
{
    public function __invoke(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.chat.pulse'), 403);

        // Verify conversation belongs to this agent
        if ($conversation->session?->agent_id !== $agent->id) {
            abort(404);
        }

        $messages = $conversation->messages()
            ->where('role', 'user')
            ->orderBy('created_at')
            ->get(['id', 'content', 'tone', 'sentiment', 'created_at']);

        $pulseData = $messages->map(function ($message, $index) {
            // Use tone score primarily, fall back to sentiment score
            $toneScore = $this->getToneScore($message->tone);
            $sentimentScore = $this->getSentimentScore($message->sentiment);
            // Prefer tone score if tone exists, otherwise use sentiment
            $score = $message->tone ? $toneScore : $sentimentScore;

            return [
                'index' => $index + 1,
                'content_preview' => str($message->content)->limit(50)->toString(),
                'tone' => $message->tone,
                'sentiment' => $message->sentiment,
                'sentiment_score' => $score,
                'tone_score' => $toneScore,
                'created_at' => $message->created_at->toISOString(),
            ];
        });

        // Calculate summary
        $summary = $this->calculateSummary($pulseData);

        return response()->json([
            'messages' => $pulseData,
            'summary' => $summary,
            'conversation' => [
                'id' => $conversation->external_id,
                'sentiment' => $conversation->sentiment,
                'tone' => $conversation->tone,
                'total_messages' => $conversation->messages()->count(),
                'user_messages' => $messages->count(),
            ],
        ]);
    }

    private function getSentimentScore(?string $sentiment): float
    {
        return match ($sentiment) {
            'positive' => 1,
            'neutral' => 0,
            'negative' => -1,
            default => 0,
        };
    }

    private function getToneScore(?string $tone): float
    {
        // Score from -1 (very negative) to 1 (very positive)
        return match ($tone) {
            'satisfied' => 1,
            'neutral' => 0,
            'confused' => -0.25,
            'urgent' => -0.5,
            'frustrated' => -1,
            default => 0,
        };
    }

    private function calculateSummary($pulseData): array
    {
        if ($pulseData->isEmpty()) {
            return [
                'started_as' => null,
                'ended_as' => null,
                'peak_negative' => null,
                'peak_positive' => null,
                'avg_sentiment' => 0,
                'trend' => 'stable',
                'resolution' => false,
            ];
        }

        $first = $pulseData->first();
        $last = $pulseData->last();

        // Find peaks
        $peakNegative = $pulseData->sortBy('sentiment_score')->first();
        $peakPositive = $pulseData->sortByDesc('sentiment_score')->first();

        // Calculate average sentiment
        $avgSentiment = $pulseData->avg('sentiment_score');

        // Determine trend
        $firstScore = $first['sentiment_score'] ?? 0;
        $lastScore = $last['sentiment_score'] ?? 0;
        $trend = 'stable';
        if ($lastScore > $firstScore + 0.5) {
            $trend = 'improving';
        } elseif ($lastScore < $firstScore - 0.5) {
            $trend = 'declining';
        }

        // Check if resolved (started negative, ended positive/neutral)
        $resolution = $firstScore < 0 && $lastScore >= 0;

        return [
            'started_as' => [
                'tone' => $first['tone'],
                'sentiment' => $first['sentiment'],
            ],
            'ended_as' => [
                'tone' => $last['tone'],
                'sentiment' => $last['sentiment'],
            ],
            'peak_negative' => $peakNegative['sentiment_score'] < 0 ? [
                'index' => $peakNegative['index'],
                'tone' => $peakNegative['tone'],
                'sentiment' => $peakNegative['sentiment'],
            ] : null,
            'peak_positive' => $peakPositive['sentiment_score'] > 0 ? [
                'index' => $peakPositive['index'],
                'tone' => $peakPositive['tone'],
                'sentiment' => $peakPositive['sentiment'],
            ] : null,
            'avg_sentiment' => round($avgSentiment, 2),
            'trend' => $trend,
            'resolution' => $resolution,
        ];
    }
}
