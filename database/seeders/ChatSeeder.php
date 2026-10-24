<?php

namespace Database\Seeders;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\Workspace;
use Database\Seeders\Demo\DemoAgents;
use Database\Seeders\Demo\DemoAnalytics;
use Database\Seeders\Demo\DemoConversations;
use Database\Seeders\Demo\DemoCountries;
use Database\Seeders\Demo\DemoRandom;
use Database\Seeders\Demo\DemoReply;
use Database\Seeders\Demo\DemoVisitors;
use Database\Seeders\Demo\SkipsInProduction;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Support\Localization\Language\Models\Language;

/**
 * Demo data: two weeks of chats for each demo agent, written from the
 * agent's own scenarios ({@see DemoConversations}). Each chat gets what the
 * app would have stored by now: a visitor session that fits the visitor
 * (country, city, device, page), the messages with the reply numbers, the
 * finished analysis (sentiment, tone, language, topic) and the matching
 * analytics events. Every scenario appears at least once per agent.
 *
 * Each run first deletes the demo agents' earlier chats. Run through
 * DatabaseSeeder, which clears the analytics too: run alone, the analytics
 * of the deleted chats stay, like those of chats a store deletes.
 * Skipped in production.
 */
class ChatSeeder extends Seeder
{
    use SkipsInProduction;

    /** How far back the chats go. */
    private const int DAYS = 14;

    /** Chats per agent: every scenario once, then random ones up to this range. */
    private const array CHATS_PER_AGENT = [10, 14];

    /** Percent of Hosted Chat conversations from a signed-in chatter. */
    private const int SIGNED_IN = 50;

    /** @var Collection<int, Chatter> */
    private Collection $chatters;

    /** @var Collection<string, int> topic name → id */
    private Collection $topics;

    /** @var Collection<string, int> language code → id */
    private Collection $languages;

    public function __construct(
        private readonly DemoAnalytics $analytics,
    ) {}

    public function run(): void
    {
        if ($this->skipInProduction()) {
            return;
        }

        $workspace = DemoAgents::workspace();
        $agents = $workspace instanceof Workspace ? DemoAgents::in($workspace) : collect();

        if (! $workspace instanceof Workspace || $agents->isEmpty()) {
            $this->command->warn('No demo agents found. Run DatabaseSeeder first.');

            return;
        }

        // Conversations, messages and topic links go with their session.
        $deleted = Session::query()->whereIn('agent_id', $agents->pluck('id'))->delete();

        if ($deleted > 0) {
            $this->command->info("Deleted {$deleted} earlier demo chat sessions.");
        }

        $this->chatters = DemoVisitors::chatters($workspace, $agents);
        $this->topics = Topic::forWorkspace($workspace)->pluck('id', 'topic');
        $this->languages = Language::query()->pluck('id', 'code');

        $chats = 0;
        $messages = 0;

        foreach ($agents as $slug => $agent) {
            foreach ($this->scenariosFor($slug) as $scenario) {
                $messages += $this->seedChat($agent, DemoAgents::AGENTS[$slug], $scenario);
                $chats++;
            }
        }

        $this->command->info("Seeded {$chats} chats with {$messages} messages.");
    }

    /**
     * Every scenario of the agent once, then random repeats, shuffled.
     *
     * @return list<array<string, mixed>>
     */
    private function scenariosFor(string $slug): array
    {
        $scenarios = DemoConversations::SCENARIOS[$slug] ?? [];

        if ($scenarios === []) {
            return [];
        }

        $plan = $scenarios;
        $total = random_int(...self::CHATS_PER_AGENT);

        while (count($plan) < $total) {
            $plan[] = $scenarios[array_rand($scenarios)];
        }

        shuffle($plan);

        return $plan;
    }

    /**
     * One chat: the session, the conversation, its messages, its topic and
     * the analytics the app would have recorded. Returns the message count.
     *
     * @param  array{surfaces: array<string, int>, pages: list<string>}  $profile
     * @param  array{title: string, topic: string, sentiment: string, tone: string, language?: string, countries?: list<string>, messages: list<array<string, mixed>>}  $scenario
     */
    private function seedChat(Agent $agent, array $profile, array $scenario): int
    {
        $source = ConversationSource::from((string) DemoRandom::pick($profile['surfaces']));
        $chatter = $this->chatterFor($source, $scenario['countries'] ?? []);
        $location = $chatter instanceof Chatter
            ? DemoCountries::in(DemoVisitors::countryOf($chatter))
            : DemoCountries::pick($scenario['countries'] ?? []);

        $timeline = $this->timeline($scenario['messages'], $this->startTime($location['timezone']));
        $startedAt = $timeline[0]['at'];
        $endedAt = array_last($timeline)['at'];
        $device = DemoVisitors::device();

        $session = Session::query()->create([
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
            'chatter_id' => $chatter?->id,
            'country_id' => $location['id'],
            'city' => $location['city'],
            'time_zone' => $location['timezone'],
            'locale' => $scenario['language'] ?? $location['locale'],
            'ip_address' => DemoVisitors::ipAddress(),
            'user_agent' => $device['user_agent'],
            'device_type' => $device['device_type'],
            'browser' => $device['browser'],
            'os' => $device['os'],
            // The Hosted Chat is its own page; the widget and the bar sit on
            // a page of the store.
            'page_url' => $source === ConversationSource::HostedChat ? null : $profile['pages'][array_rand($profile['pages'])],
            'referrer_url' => DemoVisitors::referrer(),
            'last_activity_at' => $endedAt,
            'created_at' => $startedAt,
            'updated_at' => $endedAt,
        ]);

        $conversation = new Conversation;
        $conversation->forceFill([
            'workspace_id' => $agent->workspace_id,
            'chat_session_id' => $session->id,
            'agent_id' => $agent->id,
            'source' => $source,
            'title' => $scenario['title'],
            'is_visible' => true,
            'sentiment' => $scenario['sentiment'],
            'tone' => $scenario['tone'],
            'language_id' => $this->languages->get($scenario['language'] ?? 'en'),
            // The scheduled analysis has run, so it never sends demo chats
            // to a model.
            'analyzed_at' => $endedAt->copy()->addHours(6)->min(now()),
            'analysis_attempts' => 1,
            'last_activity_at' => $endedAt,
            'created_at' => $startedAt,
            'updated_at' => $endedAt,
        ])->save();

        foreach ($timeline as $step) {
            $this->seedMessage($agent, $session, $conversation, $location['code'], $step['message'], $step['reply'], $step['at']);
        }

        if ($topicId = $this->topics->get($scenario['topic'])) {
            $conversation->topics()->attach($topicId, ['workspace_id' => $agent->workspace_id]);
            $this->analytics->topic($agent, $topicId, $startedAt);
        }

        return count($timeline);
    }

    /**
     * @param  array{role: string, content: string, sentiment?: string, tone?: string, feedback?: string, answered?: bool}  $data
     */
    private function seedMessage(Agent $agent, Session $session, Conversation $conversation, string $country, array $data, ?DemoReply $reply, Carbon $at): void
    {
        $message = Message::query()->create([
            'workspace_id' => $conversation->workspace_id,
            'chat_conversation_id' => $conversation->id,
            'source' => $conversation->source->value,
            'role' => $data['role'],
            'content' => $data['content'],
            'sentiment' => $data['sentiment'] ?? null,
            'tone' => $data['tone'] ?? null,
            'llm_id' => $reply instanceof DemoReply ? $agent->llm_id : null,
            'confidence_score' => $reply?->confidence,
            'has_llm_found_answer' => $reply?->foundAnswer,
            'response_time_ms' => $reply?->responseTimeMs,
            'prompt_tokens' => $reply?->promptTokens,
            'completion_tokens' => $reply?->completionTokens,
            'feedback' => $data['feedback'] ?? null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $sessionId = (string) $session->external_id;
        $conversationId = (string) $conversation->external_id;
        $messageId = (string) $message->external_id;

        if (! $reply instanceof DemoReply) {
            $this->analytics->visitorMessage($agent, $session->chatter_id, $sessionId, $conversationId, $messageId, $country, $at);

            if (isset($data['sentiment'], $data['tone'])) {
                $this->analytics->analyzed($agent, $data['sentiment'], $data['tone'], $at);
            }

            return;
        }

        $this->analytics->reply($agent, $session->chatter_id, $sessionId, $conversationId, $messageId, $country, $reply, $at);

        if (isset($data['feedback'])) {
            $this->analytics->feedback($agent, $session->chatter_id, $conversationId, $messageId, $data['feedback'], $country, $at->copy()->addSeconds(random_int(5, 90)));
        }
    }

    /**
     * When each message was sent: the reply follows its question after the
     * reply's response time, the next question after the visitor read and
     * typed. AI replies carry their numbers; visitor messages carry null.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return list<array{message: array<string, mixed>, reply: DemoReply|null, at: Carbon}>
     */
    private function timeline(array $messages, Carbon $start): array
    {
        $at = $start->copy();
        $timeline = [];

        foreach ($messages as $index => $message) {
            $reply = null;

            if ($message['role'] === 'assistant') {
                $reply = ($message['answered'] ?? true) ? DemoReply::answered() : DemoReply::unanswered();
                $at = $at->copy()->addMilliseconds($reply->responseTimeMs);
            } elseif ($index > 0) {
                $at = $at->copy()->addSeconds(random_int(25, 150));
            }

            $timeline[] = ['message' => $message, 'reply' => $reply, 'at' => $at];
        }

        return $timeline;
    }

    /** A start in the visitor's business hours on one of the last DAYS days. */
    private function startTime(string $timezone): Carbon
    {
        for ($try = 0; $try < 10; $try++) {
            $day = now($timezone)->subDays(random_int(0, self::DAYS - 1));

            if (($start = DemoRandom::localStart($day, $timezone)) instanceof Carbon) {
                return $start;
            }
        }

        return now()->subHours(random_int(2, 48));
    }

    /**
     * Half of the Hosted Chat conversations come from a signed-in chatter
     * (from a country the scenario allows); the widget and the bar have
     * anonymous visitors.
     *
     * @param  list<string>  $countries
     */
    private function chatterFor(ConversationSource $source, array $countries): ?Chatter
    {
        if ($source !== ConversationSource::HostedChat || ! DemoRandom::chance(self::SIGNED_IN)) {
            return null;
        }

        $candidates = $this->chatters->filter(
            fn (Chatter $chatter): bool => $countries === [] || in_array(DemoVisitors::countryOf($chatter), $countries, true),
        );

        return $candidates->isEmpty() ? null : $candidates->random();
    }
}
