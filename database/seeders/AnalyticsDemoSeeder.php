<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoCountries;
use Database\Seeders\Demo\SkipsInProduction;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\RecordFeedbackGivenAction;
use Domain\Analytics\Actions\RecordMessageAnalyzedAction;
use Domain\Analytics\Actions\RecordMessageSentAction;
use Domain\Analytics\Events\FeedbackGiven;
use Domain\Analytics\Events\MessageAnalyzed;
use Domain\Analytics\Events\MessageSent;
use Domain\Llm\Models\Llm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Telescope\Telescope;

/**
 * Seeds 30 days of "historical" analytics events that have NO backing rows in
 * chat_messages / chat_conversations — they represent the analytics that would
 * still be visible after the user deleted the underlying chats. ChatSeeder
 * lays its own (smaller) set of real chats on top, with matching analytics
 * events fired through the same Actions; together they tell the demo story
 * of "the chat log only shows recent conversations, but the analytics page
 * preserved everything."
 *
 * Run order: LlmSeeder → AnalyticsDemoSeeder → ChatSeeder.
 */
class AnalyticsDemoSeeder extends Seeder
{
    use SkipsInProduction;

    /** Days of synthetic history to fabricate. */
    private const DAYS = 30;

    /** Hour-of-day weights (UTC) — bias toward business hours. */
    private const HOUR_WEIGHTS = [
        0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
        6 => 2, 7 => 3, 8 => 4,
        9 => 7, 10 => 8, 11 => 8, 12 => 7, 13 => 7, 14 => 9, 15 => 9, 16 => 8,
        17 => 6, 18 => 5, 19 => 4, 20 => 3, 21 => 3, 22 => 2, 23 => 2,
    ];

    /** Synthetic sentiment distribution (skews positive). */
    private const SENTIMENT_WEIGHTS = ['positive' => 55, 'neutral' => 30, 'negative' => 15];

    /** Synthetic tone distribution. */
    private const TONE_WEIGHTS = ['neutral' => 40, 'satisfied' => 30, 'confused' => 12, 'frustrated' => 10, 'urgent' => 8];

    public function run(): void
    {
        // It writes analytics for every agent in the database, so a production
        // run would mix fake numbers into real customers' dashboards.
        if ($this->skipInProduction()) {
            return;
        }

        // Pulse's SlowQueries recorder captures a backtrace per query and the
        // seeder fires tens of thousands of upserts — without this, the seeder
        // OOMs at ~130MB. Telescope behaves the same way.
        if (class_exists(Pulse::class)) {
            Pulse::stopRecording();
        }
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }
        ini_set('memory_limit', '512M');

        $agents = Agent::query()->orderBy('id')->get();

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found — skipping AnalyticsDemoSeeder.');

            return;
        }

        // Pull model dimensions from the actual llms table so the per-model
        // analytics rows match real LLM names. Falls back to a constant if
        // LlmSeeder hasn't run yet (allowing standalone db:seed --class).
        $models = Llm::query()->pluck('external_name')->all();
        if (empty($models)) {
            $models = ['gpt-oss', 'qwen3'];
        }
        $modelWeights = $this->weightAround($models);

        $this->truncateAnalytics();

        /** @var RecordMessageSentAction $messageSent */
        $messageSent = app(RecordMessageSentAction::class);
        /** @var RecordFeedbackGivenAction $feedbackGiven */
        $feedbackGiven = app(RecordFeedbackGivenAction::class);
        /** @var RecordMessageAnalyzedAction $messageAnalyzed */
        $messageAnalyzed = app(RecordMessageAnalyzedAction::class);

        foreach ($agents as $agent) {
            $this->command->info(sprintf(
                'Seeding %d days of historical analytics for: %s (%s)',
                self::DAYS, $agent->name, $agent->external_id
            ));

            [$convs, $msgs, $fbs] = $this->seedAgent(
                $agent, $messageSent, $feedbackGiven, $messageAnalyzed, $modelWeights
            );

            $this->command->info(sprintf('  → %d conversations, %d messages, %d feedback events', $convs, $msgs, $fbs));
        }
    }

    /**
     * Build a weight map for an arbitrary set of llm names — first one gets
     * 60%, second 40%, additional ones split the remainder. Keeps the demo
     * skewed so the "most used models" chart has visible spread.
     *
     * @param  list<string>  $models
     * @return array<string, int>
     */
    private function weightAround(array $models): array
    {
        return match (count($models)) {
            0 => [],
            1 => [$models[0] => 100],
            default => array_combine(
                $models,
                array_pad([60, 40], count($models), max(1, (int) floor(20 / max(1, count($models) - 2))))
            ),
        };
    }

    /**
     * @param  array<string, int>  $modelWeights
     * @return array{0:int,1:int,2:int} [conversations, messages, feedback]
     */
    private function seedAgent(
        Agent $agent,
        RecordMessageSentAction $messageSent,
        RecordFeedbackGivenAction $feedbackGiven,
        RecordMessageAnalyzedAction $messageAnalyzed,
        array $modelWeights,
    ): array {
        $startDate = now()->subDays(self::DAYS - 1)->startOfDay();
        $convCount = 0;
        $msgCount = 0;
        $fbCount = 0;

        $bar = $this->command->getOutput()->createProgressBar(self::DAYS);
        $bar->start();

        for ($d = 0; $d < self::DAYS; $d++) {
            $day = $startDate->copy()->addDays($d);

            foreach (range(1, $this->conversationsForDay($day, $d)) as $_) {
                $startedAt = $day->copy()->setTime(
                    $this->weightedPick(self::HOUR_WEIGHTS),
                    random_int(0, 59),
                    random_int(0, 59),
                );

                $sessionId = (string) Str::uuid();
                $convId = (string) Str::uuid();
                $country = DemoCountries::pickCode();
                $model = (string) $this->weightedPick($modelWeights);

                // conversations_started gets incremented inside
                // RecordMessageSentAction when it detects this is the first
                // message of the conversation.
                $convCount++;

                // Exponential distribution → most chats are short, a few long.
                $msgs = max(2, min(15, (int) round($this->randExp(4))));
                $cursor = $startedAt->copy();

                for ($m = 0; $m < $msgs; $m++) {
                    $isUser = $m % 2 === 0;
                    $cursor = $cursor->copy()->addSeconds(random_int(15, 240));

                    $msgId = (string) Str::uuid();
                    $modelUsed = $isUser ? null : $model;
                    $promptTokens = $isUser ? null : random_int(120, 1400);
                    $completionTokens = $isUser ? null : random_int(60, 800);
                    $responseMs = $isUser ? null : random_int(900, 5400);

                    $messageSent->execute(new MessageSent(
                        agentId: $agent->id,
                        workspaceId: $agent->user_id,
                        chatterUserId: null,
                        conversationExternalId: $convId,
                        messageExternalId: $msgId,
                        sessionExternalId: $sessionId,
                        role: $isUser ? 'user' : 'assistant',
                        llmExternalName: $modelUsed,
                        countryCode: $country,
                        responseTimeMs: $responseMs,
                        promptTokens: $promptTokens,
                        completionTokens: $completionTokens,
                        occurredAt: $cursor,
                    ));
                    $msgCount++;

                    // ~85% of user messages get synthetic sentiment + tone so the
                    // Sentiment and Tone analytics pages have demo data; the rest
                    // stay "unspecified" (derived from total user messages).
                    if ($isUser && random_int(1, 100) <= 85) {
                        $messageAnalyzed->execute(new MessageAnalyzed(
                            agentId: $agent->id,
                            workspaceId: $agent->user_id,
                            sentiment: (string) $this->weightedPick(self::SENTIMENT_WEIGHTS),
                            tone: (string) $this->weightedPick(self::TONE_WEIGHTS),
                            occurredAt: $cursor,
                        ));
                    }

                    // ~25% of assistant turns get a reaction; positive skews 70/30.
                    if (! $isUser && random_int(1, 100) <= 25) {
                        $feedback = random_int(1, 100) <= 70 ? 'positive' : 'negative';
                        $feedbackGiven->execute(new FeedbackGiven(
                            agentId: $agent->id,
                            workspaceId: $agent->user_id,
                            chatterUserId: null,
                            conversationExternalId: $convId,
                            messageExternalId: $msgId,
                            feedback: $feedback,
                            llmExternalName: $modelUsed,
                            countryCode: $country,
                            occurredAt: $cursor->copy()->addSeconds(random_int(5, 60)),
                        ));
                        $fbCount++;
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();

        return [$convCount, $msgCount, $fbCount];
    }

    private function truncateAnalytics(): void
    {
        $connection = config('chatvia.analytics.connection');
        $db = DB::connection($connection);

        foreach ([
            'analytics_events',
            'analytics_agent_hourly_rollups',
            'analytics_conversation_metrics',
            'analytics_session_firsts',
            'analytics_agent_sentiment_daily',
            'analytics_agent_tone_daily',
            'analytics_agent_topic_daily',
            'analytics_agent_source_daily',
        ] as $table) {
            $db->table($table)->truncate();
        }
    }

    private function conversationsForDay(Carbon $day, int $dayIndex): int
    {
        // Linear ramp ~8 → ~20/day across the window, weekend ×0.6, ±50% jitter
        // so the trend chart has a real-looking slope rather than a flat line.
        $base = (int) round(8 + (($dayIndex / max(1, self::DAYS - 1)) * 12));
        if (in_array($day->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
            $base = (int) round($base * 0.6);
        }

        $lo = max(1, (int) floor($base * 0.5));
        $hi = max($lo + 1, (int) ceil($base * 1.5));

        return random_int($lo, $hi);
    }

    /**
     * @param  array<int|string, int|float>  $weights
     */
    private function weightedPick(array $weights): int|string
    {
        $total = (int) round(array_sum($weights) * 1000);
        $r = random_int(1, max(1, $total));
        $acc = 0;
        foreach ($weights as $key => $weight) {
            $acc += (int) round($weight * 1000);
            if ($r <= $acc) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    private function randExp(float $mean): float
    {
        // Inverse-CDF sample from Exp(1/$mean).
        $u = random_int(1, 999999) / 1000000;

        return -log($u) * $mean;
    }
}
