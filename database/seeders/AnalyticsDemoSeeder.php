<?php

namespace Database\Seeders;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\Workspace;
use Database\Seeders\Demo\DemoAgents;
use Database\Seeders\Demo\DemoAnalytics;
use Database\Seeders\Demo\DemoCountries;
use Database\Seeders\Demo\DemoRandom;
use Database\Seeders\Demo\DemoReply;
use Database\Seeders\Demo\DemoVisitors;
use Database\Seeders\Demo\SkipsInProduction;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;

/**
 * Demo data: 30 days of analytics for the demo agents with no chats behind
 * them, the analytics a store keeps after people delete their chats.
 * ChatSeeder adds two weeks of real chats with their own analytics on top.
 *
 * Every conversation follows its agent's profile in {@see DemoAgents}
 * (volume, topics, sentiment, tone, feedback and unanswered rates); the
 * visitors come from {@see DemoCountries} and chat in their own business
 * hours. Each run first removes the demo workspace's analytics, so a rerun
 * gives the same picture instead of doubling it. Skipped in production.
 */
class AnalyticsDemoSeeder extends Seeder
{
    use SkipsInProduction;

    /** Days of history. */
    private const int DAYS = 30;

    /** Percent of conversations from a visitor who chatted before. */
    private const int RETURNING_VISITORS = 15;

    /** Percent of conversations from a signed-in chatter. */
    private const int SIGNED_IN = 10;

    /** Percent of visitor messages the message analysis labels. */
    private const int ANALYZED = 95;

    /** Percent of conversations the conversation analysis gives a topic. */
    private const int TOPIC_ASSIGNED = 92;

    public function __construct(
        private readonly DemoAnalytics $analytics,
    ) {}

    public function run(): void
    {
        if ($this->skipInProduction()) {
            return;
        }

        // Thousands of upserts; Pulse would keep an entry for each of them.
        Pulse::stopRecording();

        $workspace = DemoAgents::workspace();
        $agents = $workspace instanceof Workspace ? DemoAgents::in($workspace) : collect();

        if (! $workspace instanceof Workspace || $agents->isEmpty()) {
            $this->command->warn('No demo agents found. Run DatabaseSeeder first.');

            return;
        }

        $this->analytics->clear($workspace);

        $chatters = DemoVisitors::chatters($workspace, $agents);
        $topics = Topic::forWorkspace($workspace)->pluck('id', 'topic');

        foreach ($agents as $slug => $agent) {
            $this->command->info('Seeding '.self::DAYS." days of analytics for {$agent->name} ({$agent->external_id})");

            [$conversations, $messages, $feedback] = $this->seedAgent($agent, DemoAgents::AGENTS[$slug], $chatters, $topics);

            $this->command->info("  → {$conversations} conversations, {$messages} messages, {$feedback} feedback events");
        }
    }

    /**
     * @param  array{daily_conversations: array{0: int, 1: int}, topics: array<string, int>, sentiment: array<string, int>, tone: array<string, int>, feedback_rate: int, unanswered_rate: int}  $profile
     * @param  Collection<int, Chatter>  $chatters
     * @param  Collection<string, int>  $topics
     * @return array{0: int, 1: int, 2: int} conversations, messages, feedback events
     */
    private function seedAgent(Agent $agent, array $profile, Collection $chatters, Collection $topics): array
    {
        $topicWeights = array_intersect_key($profile['topics'], $topics->all());
        $firstDay = now()->utc()->startOfDay()->subDays(self::DAYS - 1);
        $returning = [];
        $counts = [0, 0, 0];

        $bar = $this->command->getOutput()->createProgressBar(self::DAYS);
        $bar->start();

        for ($index = 0; $index < self::DAYS; $index++) {
            $day = $firstDay->copy()->addDays($index);

            foreach (range(1, $this->conversationsOn($day, $index, $profile['daily_conversations'])) as $_) {
                $visitor = $this->visitor($returning, $chatters);
                $startedAt = DemoRandom::localStart($day, $visitor['location']['timezone']);

                if (! $startedAt instanceof Carbon) {
                    continue;
                }

                $returning[] = $visitor;
                $returning = array_slice($returning, -40);

                [$messages, $feedback] = $this->conversation($agent, $profile, $visitor, $startedAt);
                $counts[0]++;
                $counts[1] += $messages;
                $counts[2] += $feedback;

                if ($topicWeights !== [] && DemoRandom::chance(self::TOPIC_ASSIGNED)) {
                    $this->analytics->topic($agent, $topics[DemoRandom::pick($topicWeights)], $startedAt);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();

        return $counts;
    }

    /**
     * One conversation: turns of a visitor question and an AI reply.
     *
     * @param  array{sentiment: array<string, int>, tone: array<string, int>, feedback_rate: int, unanswered_rate: int}  $profile
     * @param  array{session: string, chatter: int|null, location: array{code: string}}  $visitor
     * @return array{0: int, 1: int} messages, feedback events
     */
    private function conversation(Agent $agent, array $profile, array $visitor, Carbon $startedAt): array
    {
        $conversation = (string) Str::uuid7();
        $country = $visitor['location']['code'];
        $sentiment = (string) DemoRandom::pick($profile['sentiment']);
        $tone = DemoRandom::toneFor($sentiment, $profile['tone']);
        $turns = DemoRandom::turns(2.4);
        $at = $startedAt->copy();
        $feedback = 0;

        for ($turn = 0; $turn < $turns; $turn++) {
            $at = $at->copy()->addSeconds($turn === 0 ? 0 : random_int(20, 150));
            $this->analytics->visitorMessage($agent, $visitor['chatter'], $visitor['session'], $conversation, (string) Str::uuid7(), $country, $at);

            if (DemoRandom::chance(self::ANALYZED)) {
                // Single messages vary around the mood of the whole chat.
                $messageSentiment = DemoRandom::chance(70) ? $sentiment : 'neutral';
                $this->analytics->analyzed($agent, $messageSentiment, DemoRandom::chance(70) ? $tone : DemoRandom::toneFor($messageSentiment, $profile['tone']), $at);
            }

            $reply = DemoRandom::chance($profile['unanswered_rate']) ? DemoReply::unanswered() : DemoReply::answered();
            $at = $at->copy()->addMilliseconds($reply->responseTimeMs);
            $message = (string) Str::uuid7();
            $this->analytics->reply($agent, $visitor['chatter'], $visitor['session'], $conversation, $message, $country, $reply, $at);

            if (DemoRandom::chance($profile['feedback_rate'])) {
                $this->analytics->feedback($agent, $visitor['chatter'], $conversation, $message, $this->feedbackFor($reply, $sentiment), $country, $at->copy()->addSeconds(random_int(5, 90)));
                $feedback++;
            }
        }

        return [$turns * 2, $feedback];
    }

    /** Thumbs up mostly for good answers in chats that went well. */
    private function feedbackFor(DemoReply $reply, string $sentiment): string
    {
        $positive = match (true) {
            ! $reply->foundAnswer => 15,
            $sentiment === 'negative' => 35,
            default => 88,
        };

        return DemoRandom::chance($positive) ? 'positive' : 'negative';
    }

    /**
     * A returning visitor (same session, place and chatter), a signed-in
     * chatter from their own country, or a new anonymous visitor.
     *
     * @param  list<array{session: string, chatter: int|null, location: array{code: string, id: int|null, city: string, timezone: string, locale: string}}>  $returning
     * @param  Collection<int, Chatter>  $chatters
     * @return array{session: string, chatter: int|null, location: array{code: string, id: int|null, city: string, timezone: string, locale: string}}
     */
    private function visitor(array $returning, Collection $chatters): array
    {
        if ($returning !== [] && DemoRandom::chance(self::RETURNING_VISITORS)) {
            return $returning[array_rand($returning)];
        }

        if ($chatters->isNotEmpty() && DemoRandom::chance(self::SIGNED_IN)) {
            $chatter = $chatters->random();

            return ['session' => (string) Str::uuid7(), 'chatter' => $chatter->id, 'location' => DemoCountries::in(DemoVisitors::countryOf($chatter))];
        }

        return ['session' => (string) Str::uuid7(), 'chatter' => null, 'location' => DemoCountries::pick()];
    }

    /**
     * A growing store: the daily volume climbs from the first to the last
     * day of the range, dips on weekends and varies by ±35%.
     *
     * @param  array{0: int, 1: int}  $range
     */
    private function conversationsOn(Carbon $day, int $index, array $range): int
    {
        [$first, $last] = $range;
        $base = $first + (($last - $first) * $index / max(1, self::DAYS - 1));

        if ($day->isWeekend()) {
            $base *= 0.7;
        }

        return random_int(max(1, (int) floor($base * 0.65)), max(1, (int) ceil($base * 1.35)));
    }
}
