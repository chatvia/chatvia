<?php

declare(strict_types=1);

namespace Tests\Support\Mcp;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Services\ResponseTimeHistogram;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One fixed, fully seeded workspace for the Account-Analytics tools: two
 * agents (Alpha and Beta) with analytics events, rollups, chats and leads,
 * a monthly message limit, and a second workspace as noise. The tools must
 * answer the same numbers over it whatever the queries look like.
 */
final class AnalyticsWorkspace
{
    /**
     * Seed the workspace and its noise, at 2026-06-15 12:30.
     *
     * @return array{workspace: Workspace, alpha: Agent, beta: Agent}
     */
    public static function seed(): array
    {
        test()->travelTo(CarbonImmutable::create(2026, 6, 15, 12, 30, 0));

        $workspace = User::factory()->create()->workspace();
        $alpha = Agent::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Alpha']);
        $beta = Agent::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Beta']);

        $other = User::factory()->create()->workspace();
        $noise = Agent::factory()->create(['workspace_id' => $other->id, 'name' => 'Noise']);

        self::messageLimit($workspace);
        self::events($workspace, $alpha, $beta, $other, $noise);
        self::rollups($workspace, $alpha, $beta, $other, $noise);
        self::chats($alpha, $beta, $noise);

        return ['workspace' => $workspace, 'alpha' => $alpha, 'beta' => $beta];
    }

    private static function messageLimit(Workspace $workspace): void
    {
        MessageLimit::factory()->forWorkspace($workspace)->create(['monthly_limit' => 1000, 'messages_used' => 420]);
    }

    private static function events(Workspace $workspace, Agent $alpha, Agent $beta, Workspace $other, Agent $noise): void
    {
        $chatterOne = Chatter::factory()->ofWorkspace($workspace)->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
        $chatterTwo = Chatter::factory()->ofWorkspace($workspace)->create(['name' => 'Grace Hopper', 'email' => 'grace@example.test']);
        $noiseChatter = Chatter::factory()->ofWorkspace($other)->create(['name' => 'Noise Chatter']);

        $conversations = [
            'one' => '00000000-0000-4000-8000-000000000001',
            'two' => '00000000-0000-4000-8000-000000000002',
            'three' => '00000000-0000-4000-8000-000000000003',
            'old' => '00000000-0000-4000-8000-000000000004',
            'noise' => '00000000-0000-4000-8000-000000000005',
        ];

        $event = fn (int $workspaceId, Agent $agent, string $conversation, string $at, array $extra = []): array => [
            'agent_id' => $agent->id,
            'workspace_id' => $workspaceId,
            'chatter_id' => null,
            'role' => null,
            'response_time_ms' => null,
            'conversation_external_id' => $conversations[$conversation],
            'message_external_id' => (string) Str::uuid(),
            'session_external_id' => '00000000-0000-4000-9000-000000000001',
            'occurred_at' => $at,
            'inserted_at' => $at,
            ...$extra,
        ];

        $user = fn (?Chatter $chatter): array => ['role' => 'user', 'chatter_id' => $chatter?->id];
        $reply = fn (int $ms): array => ['role' => 'assistant', 'response_time_ms' => $ms];

        self::db()->table('analytics_events')->insert([
            $event($workspace->id, $alpha, 'one', '2026-06-10 09:15:00', $user($chatterOne)),
            $event($workspace->id, $alpha, 'one', '2026-06-10 09:15:02', $reply(1200)),
            $event($workspace->id, $alpha, 'one', '2026-06-10 09:20:00', $user($chatterOne)),
            $event($workspace->id, $alpha, 'one', '2026-06-10 09:20:01', $reply(900)),
            $event($workspace->id, $alpha, 'two', '2026-06-12 14:00:00', $user($chatterTwo)),
            $event($workspace->id, $alpha, 'two', '2026-06-12 14:00:05', $reply(800)),
            $event($workspace->id, $beta, 'three', '2026-06-14 20:10:00', $user(null)),
            $event($workspace->id, $beta, 'three', '2026-06-14 20:10:03', $reply(3000)),
            $event($workspace->id, $beta, 'old', '2026-05-20 10:00:00', $user($chatterOne)),
            $event($workspace->id, $beta, 'old', '2026-05-20 10:00:02', $reply(1500)),
            $event($other->id, $noise, 'noise', '2026-06-11 11:00:00', $user($noiseChatter)),
            $event($other->id, $noise, 'noise', '2026-06-11 11:00:01', $reply(99)),
        ]);

        // Each rated message's current reaction.
        $feedback = fn (int $workspaceId, Agent $agent, string $conversation, string $value, string $at): array => [
            'message_external_id' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'workspace_id' => $workspaceId,
            'conversation_external_id' => $conversations[$conversation],
            'feedback' => $value,
            'occurred_at' => $at,
        ];

        self::db()->table('analytics_message_feedback')->insert([
            $feedback($workspace->id, $alpha, 'one', 'positive', '2026-06-10 09:16:00'),
            $feedback($workspace->id, $beta, 'three', 'negative', '2026-06-14 20:11:00'),
            $feedback($workspace->id, $beta, 'three', 'positive', '2026-06-14 20:12:00'),
            $feedback($other->id, $noise, 'noise', 'negative', '2026-06-11 11:01:00'),
        ]);

        // The first visitor message and first reply of each conversation, as the
        // recorder keeps them for the events above.
        self::db()->table('analytics_conversation_metrics')->insert([
            ['conversation_external_id' => $conversations['one'], 'source' => 'widget', 'agent_id' => $alpha->id, 'workspace_id' => $workspace->id, 'started_at' => '2026-06-10 09:15:00', 'ended_at' => '2026-06-10 09:20:01', 'first_user_at' => '2026-06-10 09:15:00', 'first_assistant_at' => '2026-06-10 09:15:02', 'message_count' => 4, 'duration_seconds' => 301],
            ['conversation_external_id' => $conversations['two'], 'source' => 'hosted_chat', 'agent_id' => $alpha->id, 'workspace_id' => $workspace->id, 'started_at' => '2026-06-12 14:00:00', 'ended_at' => '2026-06-12 14:00:05', 'first_user_at' => '2026-06-12 14:00:00', 'first_assistant_at' => '2026-06-12 14:00:05', 'message_count' => 2, 'duration_seconds' => 0],
            ['conversation_external_id' => $conversations['three'], 'source' => 'telegram', 'agent_id' => $beta->id, 'workspace_id' => $workspace->id, 'started_at' => '2026-06-14 20:10:00', 'ended_at' => '2026-06-14 20:10:03', 'first_user_at' => '2026-06-14 20:10:00', 'first_assistant_at' => '2026-06-14 20:10:03', 'message_count' => 2, 'duration_seconds' => 3],
            ['conversation_external_id' => $conversations['old'], 'source' => 'widget', 'agent_id' => $beta->id, 'workspace_id' => $workspace->id, 'started_at' => '2026-05-20 10:00:00', 'ended_at' => '2026-05-20 10:00:02', 'first_user_at' => '2026-05-20 10:00:00', 'first_assistant_at' => '2026-05-20 10:00:02', 'message_count' => 2, 'duration_seconds' => 2],
            ['conversation_external_id' => $conversations['noise'], 'source' => 'slack', 'agent_id' => $noise->id, 'workspace_id' => $other->id, 'started_at' => '2026-06-11 11:00:00', 'ended_at' => '2026-06-11 11:00:01', 'first_user_at' => '2026-06-11 11:00:00', 'first_assistant_at' => '2026-06-11 11:00:01', 'message_count' => 50, 'duration_seconds' => 999],
        ]);

        self::db()->table('analytics_session_firsts')->insert([
            ['agent_id' => $alpha->id, 'workspace_id' => $workspace->id, 'session_external_id' => '00000000-0000-4000-9000-000000000011', 'device_type' => 'desktop', 'browser' => 'Chrome', 'os' => 'macOS', 'first_seen_at' => '2026-06-10 09:14:00'],
            ['agent_id' => $alpha->id, 'workspace_id' => $workspace->id, 'session_external_id' => '00000000-0000-4000-9000-000000000012', 'device_type' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS', 'first_seen_at' => '2026-06-12 13:59:00'],
            ['agent_id' => $beta->id, 'workspace_id' => $workspace->id, 'session_external_id' => '00000000-0000-4000-9000-000000000013', 'device_type' => 'mobile', 'browser' => 'Chrome', 'os' => 'Android', 'first_seen_at' => '2026-06-14 20:09:00'],
            ['agent_id' => $beta->id, 'workspace_id' => $workspace->id, 'session_external_id' => '00000000-0000-4000-9000-000000000014', 'device_type' => 'mobile', 'browser' => 'Chrome', 'os' => 'Android', 'first_seen_at' => '2026-05-20 09:59:00'],
            ['agent_id' => $noise->id, 'workspace_id' => $other->id, 'session_external_id' => '00000000-0000-4000-9000-000000000015', 'device_type' => 'tablet', 'browser' => 'Edge', 'os' => 'Windows', 'first_seen_at' => '2026-06-11 10:59:00'],
        ]);
    }

    private static function rollups(Workspace $workspace, Agent $alpha, Agent $beta, Workspace $other, Agent $noise): void
    {
        // Tokens and reply times match the replies seeded in analytics_events for
        // the same hour, and the answer figures the replies seeded in
        // chat_messages; the hours with no events behind them have none. The
        // country goes to the daily starts by country, as the recorder does.
        $countries = [];
        $hour = function (int $workspaceId, Agent $agent, string $bucket, ?string $country, int $conversations, int $user, int $assistant, array $usage = []) use (&$countries): array {
            if ($country !== null) {
                $countries[] = ['agent_id' => $agent->id, 'workspace_id' => $workspaceId, 'bucket_day' => Str::before($bucket, ' '), 'country_code' => $country, 'conversations_started' => $conversations];
            }

            $histogram = array_fill(0, ResponseTimeHistogram::BUCKETS, 0);

            foreach ($usage['times'] ?? [] as $ms) {
                $histogram[ResponseTimeHistogram::bucketOf($ms)]++;
            }

            return [
                'agent_id' => $agent->id,
                'workspace_id' => $workspaceId,
                'bucket_hour' => $bucket,
                'conversations_started' => $conversations,
                'user_messages' => $user,
                'assistant_messages' => $assistant,
                'prompt_tokens' => $usage['prompt'] ?? 0,
                'completion_tokens' => $usage['completion'] ?? 0,
                'total_tokens' => ($usage['prompt'] ?? 0) + ($usage['completion'] ?? 0),
                'response_ms_sum' => array_sum($usage['times'] ?? []),
                'response_ms_count' => count($usage['times'] ?? []),
                'response_ms_max' => max([0, ...$usage['times'] ?? []]),
                'response_ms_hist' => ResponseTimeHistogram::literal($histogram),
                'unanswered_replies' => $usage['unanswered'] ?? 0,
                'confidence_sum' => $usage['confidence_sum'] ?? 0,
                'confidence_count' => $usage['confidence_count'] ?? 0,
            ];
        };

        $hours = [
            $hour($workspace->id, $alpha, '2026-06-10 09:00:00', 'no', 1, 2, 2, ['prompt' => 180, 'completion' => 90, 'times' => [1200, 900], 'confidence_sum' => 1.7, 'confidence_count' => 2]),
            $hour($workspace->id, $alpha, '2026-06-12 14:00:00', 'de', 2, 1, 1, ['prompt' => 120, 'completion' => 80, 'times' => [800], 'unanswered' => 1, 'confidence_sum' => 0.4, 'confidence_count' => 1]),
            $hour($workspace->id, $beta, '2026-06-14 20:00:00', 'us', 2, 1, 1, ['prompt' => 60, 'completion' => 30, 'times' => [3000]]),
            $hour($workspace->id, $beta, '2026-06-14 21:00:00', null, 3, 1, 1),
            $hour($workspace->id, $beta, '2026-06-08 09:00:00', 'de', 2, 4, 4),
            $hour($workspace->id, $beta, '2026-05-20 10:00:00', 'no', 1, 1, 1, ['prompt' => 10, 'completion' => 10, 'times' => [1500]]),
            $hour($other->id, $noise, '2026-06-11 11:00:00', 'us', 40, 40, 40, ['prompt' => 999, 'completion' => 999, 'times' => [99], 'unanswered' => 1, 'confidence_sum' => 0.1, 'confidence_count' => 1]),
        ];

        self::db()->table('analytics_agent_hourly_rollups')->insert($hours);
        self::db()->table('analytics_agent_country_daily')->insert($countries);
        self::db()->table('analytics_agent_daily')->insert(self::days($hours));

        $day = fn (int $workspaceId, Agent $agent, string $bucket, array $columns): array => [
            'agent_id' => $agent->id,
            'workspace_id' => $workspaceId,
            'bucket_day' => $bucket,
            ...$columns,
        ];

        self::db()->table('analytics_agent_sentiment_daily')->insert([
            $day($workspace->id, $alpha, '2026-06-10', ['sentiment' => 'positive', 'message_count' => 3]),
            $day($workspace->id, $alpha, '2026-06-12', ['sentiment' => 'neutral', 'message_count' => 2]),
            $day($workspace->id, $beta, '2026-06-14', ['sentiment' => 'negative', 'message_count' => 1]),
            $day($other->id, $noise, '2026-06-11', ['sentiment' => 'negative', 'message_count' => 50]),
        ]);

        $pricing = Topic::factory()->create(['workspace_id' => $workspace->id, 'topic' => 'Pricing', 'description' => 'Plans and prices'])->id;
        $shipping = Topic::factory()->create(['workspace_id' => $workspace->id, 'topic' => 'Shipping', 'description' => 'Delivery questions'])->id;

        self::db()->table('analytics_agent_topic_daily')->insert([
            $day($workspace->id, $alpha, '2026-06-10', ['topic_id' => $pricing, 'conversation_count' => 2]),
            $day($workspace->id, $alpha, '2026-06-12', ['topic_id' => $pricing, 'conversation_count' => 1]),
            $day($workspace->id, $beta, '2026-06-14', ['topic_id' => $shipping, 'conversation_count' => 1]),
            $day($other->id, $noise, '2026-06-11', ['topic_id' => $shipping, 'conversation_count' => 30]),
        ]);

        self::db()->table('analytics_agent_function_daily')->insert([
            $day($workspace->id, $alpha, '2026-06-10', ['function_id' => 11, 'function_name' => 'lookup_order', 'invocation_count' => 5]),
            $day($workspace->id, $beta, '2026-06-14', ['function_id' => 12, 'function_name' => 'create_ticket', 'invocation_count' => 2]),
            $day($other->id, $noise, '2026-06-11', ['function_id' => 13, 'function_name' => 'noise_fn', 'invocation_count' => 90]),
        ]);

        self::db()->table('analytics_agent_mcp_tool_daily')->insert([
            $day($workspace->id, $alpha, '2026-06-10', ['server_slug' => 'charts', 'tool_name' => 'render-bar-chart-tool', 'server_name' => 'Charts', 'invocation_count' => 4]),
            $day($workspace->id, $beta, '2026-06-14', ['server_slug' => 'weather', 'tool_name' => 'current-weather-tool', 'server_name' => 'Weather', 'invocation_count' => 1]),
        ]);

        self::db()->table('analytics_agent_source_daily')->insert([
            $day($workspace->id, $alpha, '2026-06-10', ['sourceable_type' => 'source_file', 'sourceable_id' => 1, 'source_name' => 'Handbook.pdf', 'retrieval_count' => 7]),
            $day($workspace->id, $beta, '2026-06-14', ['sourceable_type' => 'source_website', 'sourceable_id' => 2, 'source_name' => null, 'retrieval_count' => 3]),
        ]);
    }

    private static function chats(Agent $alpha, Agent $beta, Agent $noise): void
    {
        $session = fn (Agent $agent, string $device, string $browser, string $os, string $at): Session => Session::factory()->create([
            'agent_id' => $agent->id,
            'device_type' => $device,
            'browser' => $browser,
            'os' => $os,
            'created_at' => $at,
        ]);

        $desktop = $session($alpha, 'desktop', 'Chrome', 'macOS', '2026-06-10 09:14:00');
        $phone = $session($alpha, 'mobile', 'Safari', 'iOS', '2026-06-12 13:59:00');
        $secondPhone = $session($alpha, 'mobile', 'Safari', 'iOS', '2026-06-13 08:00:00');
        $android = $session($beta, 'mobile', 'Chrome', 'Android', '2026-06-14 20:09:00');
        $secondAndroid = $session($beta, 'mobile', 'Chrome', 'Android', '2026-06-14 22:00:00');
        $thirdAndroid = $session($beta, 'mobile', 'Chrome', 'Android', '2026-06-09 07:00:00');
        $noiseSession = $session($noise, 'tablet', 'Edge', 'Windows', '2026-06-11 10:59:00');

        $conversation = fn (Session $session, ConversationSource $source, string $at, array $extra = []): Conversation => Conversation::factory()->create([
            'chat_session_id' => $session->id,
            'agent_id' => $session->agent_id,
            'source' => $source,
            'is_visible' => true,
            'created_at' => $at,
            ...$extra,
        ]);

        $widget = $conversation($desktop, ConversationSource::Widget, '2026-06-10 09:15:00');
        $hosted = $conversation($phone, ConversationSource::HostedChat, '2026-06-12 14:00:00', ['handoff_at' => '2026-06-12 14:01:00', 'handoff_status' => 'human']);
        $telegram = $conversation($android, ConversationSource::Telegram, '2026-06-14 20:10:00', ['handoff_at' => '2026-06-14 20:11:00', 'handoff_status' => 'pending']);
        $conversation($secondPhone, ConversationSource::Widget, '2026-06-13 08:01:00');
        $conversation($secondAndroid, ConversationSource::Widget, '2026-06-14 22:01:00');
        $conversation($thirdAndroid, ConversationSource::Telegram, '2026-06-09 07:01:00', ['handoff_status' => 'ai']);
        $conversation($android, ConversationSource::Widget, '2026-06-14 20:20:00', ['deleted_at' => '2026-06-14 21:00:00']);
        $conversation($android, ConversationSource::Widget, '2026-05-21 08:00:00', ['handoff_status' => 'pending']);
        $noiseConversation = $conversation($noiseSession, ConversationSource::Slack, '2026-06-11 11:00:00', ['handoff_status' => 'pending']);
        // Opened but never used: not a conversation in any count.
        $conversation($secondPhone, ConversationSource::HostedChat, '2026-06-13 09:00:00', ['is_visible' => false, 'handoff_status' => 'pending']);

        $reply = fn (Conversation $conversation, ?bool $found, ?float $confidence, string $at): Message => Message::factory()->create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'has_llm_found_answer' => $found,
            'confidence_score' => $confidence,
            'created_at' => $at,
        ]);

        $reply($widget, true, 0.9, '2026-06-10 09:15:02');
        $reply($widget, true, 0.8, '2026-06-10 09:20:01');
        $reply($hosted, false, 0.4, '2026-06-12 14:00:05');
        $reply($telegram, true, null, '2026-06-14 20:10:03');
        $reply($noiseConversation, false, 0.1, '2026-06-11 11:00:01');

        $form = FormDefinition::factory()->forAgent($alpha)->create(['slug' => 'contact-us', 'is_active' => true]);
        FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $widget->id, 'created_at' => '2026-06-10 09:30:00', 'submitted_at' => '2026-06-10 09:30:00']);
        FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $hosted->id, 'created_at' => '2026-06-12 14:05:00', 'submitted_at' => '2026-06-12 14:05:00']);
        FormSubmission::factory()->forDefinition($form)->create(['status' => FormSubmissionStatus::Pending, 'chat_conversation_id' => $hosted->id, 'created_at' => '2026-06-12 14:06:00']);

        $betaForm = FormDefinition::factory()->forAgent($beta)->create(['slug' => 'callback', 'is_active' => true]);
        FormSubmission::factory()->forDefinition($betaForm)->submitted()->create(['created_at' => '2026-05-21 08:05:00', 'submitted_at' => '2026-05-21 08:05:00']);
    }

    /**
     * The daily rollup rows the hourly ones add up to.
     *
     * @param  list<array<string, mixed>>  $hours
     * @return list<array<string, mixed>>
     */
    private static function days(array $hours): array
    {
        return collect($hours)
            ->groupBy(fn (array $hour): string => $hour['agent_id'].' '.Str::before($hour['bucket_hour'], ' '))
            ->map(function (Collection $day): array {
                $first = $day->first();
                $histogram = $day->reduce(
                    fn (array $sum, array $hour): array => array_map(fn (int $a, string $b): int => $a + (int) $b, $sum, Str::of($hour['response_ms_hist'])->trim('{}')->explode(',')->all()),
                    array_fill(0, ResponseTimeHistogram::BUCKETS, 0),
                );

                return [
                    'agent_id' => $first['agent_id'],
                    'workspace_id' => $first['workspace_id'],
                    'bucket_day' => Str::before($first['bucket_hour'], ' '),
                    ...collect(['conversations_started', 'user_messages', 'assistant_messages', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'response_ms_sum', 'response_ms_count', 'unanswered_replies', 'confidence_sum', 'confidence_count'])
                        ->mapWithKeys(fn (string $column): array => [$column => $day->sum($column)])
                        ->all(),
                    'response_ms_max' => $day->max('response_ms_max'),
                    'response_ms_hist' => ResponseTimeHistogram::literal($histogram),
                ];
            })
            ->values()
            ->all();
    }

    private static function db(): Connection
    {
        return DB::connection(config('chatvia.analytics.connection'));
    }
}
