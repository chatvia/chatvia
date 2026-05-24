<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Data\Resources\ChatLog\ChatLogConversationsResourceData;
use Domain\Agents\Data\Resources\ChatLog\ChatLogMessagesResourceData;
use Domain\Agents\Data\Resources\ChatLog\ChatLogTopicsResourceData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use App\Country\Models\Country;
use Domain\Agents\Models\Lead;
use Domain\Chat\Services\Memory\ChatMemory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentChatLogController
{
    public function index(Request $request, Agent $agent): Response
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        return Inertia::render('agents/ChatLog/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'current_conversation_id' => $request->query('conversation_id'),
        ]);
    }

    public function conversations(Request $request, Agent $agent): ChatLogConversationsResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        return ChatLogConversationsResourceData::fromAgent(
            agent: $agent,
            page: $request->integer('page', 1),
            topicFilter: $request->query('topic'),
            sentimentFilter: $request->query('sentiment'),
            toneFilter: $request->query('tone'),
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date'),
            countryFilter: $request->query('country'),
            confidenceScoreFilter: $request->query('confidence_score'),
            feedbackFilter: $request->query('feedback'),
            sourceFilter: $request->query('source'),
            onlineOnly: $request->boolean('online_only'),
        );
    }

    public function messages(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        if ($conversation->session?->agent_id !== $agent->id) {
            abort(404);
        }

        $messages = ChatMessage::query()
            ->with('llm')
            ->forAgentConversation($agent->id, $conversation->id)
            ->orderBy('created_at')
            ->orderByRaw("CASE WHEN role = 'user' THEN 0 ELSE 1 END")
            ->paginate(50, page: $request->integer('page', 1));

        $lead = Lead::query()
            ->where('agent_id', $agent->id)
            ->where('chat_conversation_id', $conversation->id)
            ->first();

        $conversation->loadMissing('handoffUser');

        $data = ChatLogMessagesResourceData::fromPaginator($messages, $lead)->toArray();
        $data['handoff'] = [
            'status' => $conversation->handoff_status ?? 'ai',
            'admin_id' => $conversation->handoff_user_id,
            'admin_name' => $conversation->handoffUser?->name,
            'handoff_at' => $conversation->handoff_at?->toIso8601String(),
        ];

        return response()->json($data);
    }

    public function pulse(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.pulse'), 403);

        if ($conversation->session?->agent_id !== $agent->id) {
            abort(404);
        }

        $messages = $conversation->messages()
            ->where('role', 'user')
            ->orderBy('created_at')
            ->get(['id', 'content', 'tone', 'sentiment', 'created_at']);

        $pulseData = $messages->map(function ($message, $index) {
            $toneScore = $this->toneScore($message->tone);
            $sentimentScore = $this->sentimentScore($message->sentiment);
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

        return response()->json([
            'messages' => $pulseData,
            'summary' => $this->pulseSummary($pulseData),
            'conversation' => [
                'id' => $conversation->external_id,
                'sentiment' => $conversation->sentiment,
                'tone' => $conversation->tone,
                'total_messages' => $conversation->messages()->count(),
                'user_messages' => $messages->count(),
            ],
        ]);
    }

    public function topics(Request $request, Agent $agent): ChatLogTopicsResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        $topics = $agent->topics()
            ->orderBy('chat_topics.topic')
            ->get(['chat_topics.id', 'chat_topics.topic', 'chat_topics.description']);

        return ChatLogTopicsResourceData::fromCollection($topics);
    }

    public function countries(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        $countryIds = ChatSession::query()
            ->where('agent_id', $agent->id)
            ->whereNotNull('country_id')
            ->distinct()
            ->pluck('country_id');

        $countries = Country::query()
            ->whereIn('id', $countryIds)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json(['countries' => $countries]);
    }

    public function destroyConversation(
        Request $request,
        Agent $agent,
        ChatMemory $chatMemory,
        string $conversationId,
    ): JsonResponse {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.delete'), 403);

        $conversation = ChatConversation::query()
            ->whereHas('session', fn ($q) => $q->where('agent_id', $agent->id))
            ->where('external_id', $conversationId)
            ->firstOrFail();

        $session = $conversation->session;

        $chatMemory
            ->setAgent($agent)
            ->setExternalSessionId($session->external_id)
            ->setConversationId($conversation->external_id)
            ->deleteConversation(deletedBy: 'admin');

        $conversation->delete();

        if ($session) {
            $remaining = $session->conversations()->count();

            if ($remaining === 0
                && $session->last_activity_at
                && $session->last_activity_at->lt(now()->subDays(30))
            ) {
                $session->delete();
            }
        }

        return response()->json(['success' => true, 'message' => 'Conversation deleted successfully.']);
    }

    private function sentimentScore(?string $sentiment): float
    {
        return match ($sentiment) {
            'positive' => 1,
            'neutral' => 0,
            'negative' => -1,
            default => 0,
        };
    }

    private function toneScore(?string $tone): float
    {
        return match ($tone) {
            'satisfied' => 1,
            'neutral' => 0,
            'confused' => -0.25,
            'urgent' => -0.5,
            'frustrated' => -1,
            default => 0,
        };
    }

    private function pulseSummary($pulseData): array
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
        $peakNegative = $pulseData->sortBy('sentiment_score')->first();
        $peakPositive = $pulseData->sortByDesc('sentiment_score')->first();
        $avgSentiment = $pulseData->avg('sentiment_score');

        $firstScore = $first['sentiment_score'] ?? 0;
        $lastScore = $last['sentiment_score'] ?? 0;

        $trend = 'stable';
        if ($lastScore > $firstScore + 0.5) {
            $trend = 'improving';
        } elseif ($lastScore < $firstScore - 0.5) {
            $trend = 'declining';
        }

        return [
            'started_as' => ['tone' => $first['tone'], 'sentiment' => $first['sentiment']],
            'ended_as' => ['tone' => $last['tone'], 'sentiment' => $last['sentiment']],
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
            'resolution' => $firstScore < 0 && $lastScore >= 0,
        ];
    }
}
