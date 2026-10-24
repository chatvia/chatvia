<?php

namespace Database\Seeders\Demo;

use App\Identity\Chatter\Enums\ChatterSource;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who chats with the demo agents: the devices they use (type, OS, browser
 * and a matching user-agent string, so the session details never contradict
 * each other) and the few signed-in chatters of the demo workspace, each
 * living in one of the {@see DemoCountries} markets.
 */
class DemoVisitors
{
    /**
     * Device profiles with their share of the traffic.
     *
     * @var list<array{weight: int, device_type: string, os: string, browser: string, user_agent: string}>
     */
    public const array DEVICES = [
        ['weight' => 26, 'device_type' => 'desktop', 'os' => 'Windows', 'browser' => 'Chrome',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'],
        ['weight' => 7, 'device_type' => 'desktop', 'os' => 'Windows', 'browser' => 'Edge',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0'],
        ['weight' => 12, 'device_type' => 'desktop', 'os' => 'macOS', 'browser' => 'Safari',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15'],
        ['weight' => 9, 'device_type' => 'desktop', 'os' => 'macOS', 'browser' => 'Chrome',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'],
        ['weight' => 3, 'device_type' => 'desktop', 'os' => 'Linux', 'browser' => 'Firefox',
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:144.0) Gecko/20100101 Firefox/144.0'],
        ['weight' => 22, 'device_type' => 'mobile', 'os' => 'iOS', 'browser' => 'Safari',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1'],
        ['weight' => 16, 'device_type' => 'mobile', 'os' => 'Android', 'browser' => 'Chrome',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36'],
        ['weight' => 5, 'device_type' => 'tablet', 'os' => 'iOS', 'browser' => 'Safari',
            'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1'],
    ];

    /**
     * The demo workspace's signed-in chatters. They chat in the Hosted Chat,
     * always from their own country.
     *
     * @var list<array{name: string, email: string, country: string, locale: string}>
     */
    public const array CHATTERS = [
        ['name' => 'Maya Thompson', 'email' => 'maya.thompson@example.com', 'country' => 'us', 'locale' => 'en'],
        ['name' => 'Daniel Brooks', 'email' => 'daniel.brooks@example.com', 'country' => 'us', 'locale' => 'en'],
        ['name' => 'Oliver Bennett', 'email' => 'oliver.bennett@example.co.uk', 'country' => 'gb', 'locale' => 'en'],
        ['name' => 'Noah Tremblay', 'email' => 'noah.tremblay@example.ca', 'country' => 'ca', 'locale' => 'en'],
        ['name' => 'Lena Fischer', 'email' => 'lena.fischer@example.de', 'country' => 'de', 'locale' => 'de'],
        ['name' => 'Sofía García', 'email' => 'sofia.garcia@example.es', 'country' => 'es', 'locale' => 'es'],
        ['name' => 'Priya Nair', 'email' => 'priya.nair@example.in', 'country' => 'in', 'locale' => 'en'],
        ['name' => 'Emma Lindqvist', 'email' => 'emma.lindqvist@example.se', 'country' => 'se', 'locale' => 'sv'],
    ];

    /**
     * Where a visitor came from before opening the chat (null: typed the
     * address or used a bookmark).
     *
     * @var array<string, int>
     */
    public const array REFERRERS = [
        '' => 30,
        'https://www.google.com/' => 34,
        'https://www.bing.com/' => 5,
        'https://duckduckgo.com/' => 3,
        'https://mail.google.com/' => 9,
        'https://www.instagram.com/' => 6,
        'https://www.reddit.com/r/homeautomation/' => 5,
        'https://www.youtube.com/' => 8,
    ];

    /**
     * The demo chatters of $workspace, created on the first call, each a
     * member of every given agent. Keyed by chatter id.
     *
     * @param  iterable<Agent>  $agents
     * @return Collection<int, Chatter>
     */
    public static function chatters(Workspace $workspace, iterable $agents): Collection
    {
        $chatters = collect(self::CHATTERS)->map(fn (array $chatter): Chatter => Chatter::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'email' => $chatter['email']],
            [
                'name' => $chatter['name'],
                'locale' => $chatter['locale'],
                'source' => ChatterSource::Dashboard,
                'email_verified_at' => now()->subMonths(2),
            ],
        ))->keyBy('id');

        foreach ($agents as $agent) {
            DB::table('agent_chatter')->insertOrIgnore($chatters->keys()->map(fn (int $chatterId): array => [
                'workspace_id' => $workspace->id,
                'agent_id' => $agent->id,
                'chatter_id' => $chatterId,
            ])->all());
        }

        return $chatters;
    }

    /** The country of a demo chatter. */
    public static function countryOf(Chatter $chatter): string
    {
        return collect(self::CHATTERS)->firstWhere('email', $chatter->email)['country'] ?? 'us';
    }

    /** @return array{weight: int, device_type: string, os: string, browser: string, user_agent: string} */
    public static function device(): array
    {
        return self::DEVICES[DemoRandom::pick(array_column(self::DEVICES, 'weight'))];
    }

    public static function referrer(): ?string
    {
        return ((string) DemoRandom::pick(self::REFERRERS)) ?: null;
    }

    /** A made-up public address from the documentation ranges (RFC 5737). */
    public static function ipAddress(): string
    {
        $range = ['192.0.2', '198.51.100', '203.0.113'][random_int(0, 2)];

        return $range.'.'.random_int(1, 254);
    }
}
