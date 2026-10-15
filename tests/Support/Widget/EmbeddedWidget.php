<?php

declare(strict_types=1);

namespace Tests\Support\Widget;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Services\WidgetAuthSessionService;
use Domain\Agent\Models\Agent;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\Request;

/**
 * Calls the `/widget/*` API the way the embedded widget does on a customer's
 * site: the agent id header from an allowed origin, plus the session and
 * conversation ids once the widget has booted (and, for a signed-in chatter,
 * their widget session token).
 */
final class EmbeddedWidget
{
    /** The customer site the widget is embedded on. */
    public const string SITE = 'customer.example';

    /** The browser a signed-in chatter's widget session token is bound to. */
    private const string USER_AGENT = 'Widget test';

    /** Let the agent's widget run on {@see SITE}, and only there. */
    public static function allowSite(Agent $agent): void
    {
        config(['chatvia.widget.always_allowed_origins' => []]);
        $agent->setSetting('sites', [self::SITE], 'widget');
    }

    /**
     * The headers of every widget call, before a session exists.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public static function headers(Agent $agent, array $extra = []): array
    {
        return [WidgetHeaders::AGENT_ID => $agent->external_id, 'Origin' => 'https://'.self::SITE, ...$extra];
    }

    /**
     * The headers of a widget call by a signed-in chatter: a widget session
     * token as the auth exchange mints it, bound to the test client's IP and
     * user agent.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public static function signedInHeaders(Agent $agent, Chatter $chatter, array $extra = []): array
    {
        $client = Request::create('/widget', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => self::USER_AGENT]);

        return self::headers($agent, [
            WidgetHeaders::AUTH_TOKEN => app(WidgetAuthSessionService::class)->issue($chatter, $agent, $client)['token'],
            'User-Agent' => self::USER_AGENT,
            ...$extra,
        ]);
    }

    /**
     * Boot the widget as a signed-in chatter: a new session of theirs with
     * its first conversation.
     *
     * @return array{session_id: string, conversation_id: string}
     */
    public static function bootSignedIn(Agent $agent, Chatter $chatter): array
    {
        return test()->flushHeaders()
            ->withHeaders(self::signedInHeaders($agent, $chatter))
            ->postJson('/widget/sessions/init')
            ->assertSuccessful()
            ->json();
    }

    /**
     * Boot the widget as a new visitor: a new session with its first
     * conversation. Headers set by earlier calls are dropped first, so an
     * earlier visitor's session id is never sent along and resumed.
     *
     * @return array{session_id: string, conversation_id: string}
     */
    public static function boot(Agent $agent): array
    {
        return test()->flushHeaders()
            ->withHeaders(self::headers($agent))
            ->postJson('/widget/sessions/init')
            ->assertSuccessful()
            ->json();
    }

    /**
     * The headers of a chat call in a freshly booted session and conversation.
     *
     * @return array<string, string>
     */
    public static function chatHeaders(Agent $agent): array
    {
        $boot = self::boot($agent);

        return self::headers($agent, [
            WidgetHeaders::SESSION_ID => $boot['session_id'],
            WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
        ]);
    }
}
