<?php

namespace App\Http\Widget;

use Attribute;
use Domain\Agent\Models\Agent;
use Domain\Widget\WidgetHeaders;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Http\Request;
use ReflectionParameter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The agent a widget API call is for, named by the external id the embedded
 * widget sends in the X-AGENT-ID header ({@see WidgetHeaders::AGENT_ID}).
 *
 * Put it on a controller or FormRequest parameter to receive the agent:
 * `#[WidgetAgent] Agent $agent`. A nullable parameter receives null when the
 * header is missing or names no agent; a non-nullable one turns that into a
 * 404. Middleware, rate limiters and other code holding a request call
 * {@see from()}.
 *
 * The lookup runs once per request: it is memoised on the request's attribute
 * bag, keyed by the header value, so the origin check, the access gate, the
 * rate limiter and the controller share one query, and a changed header never
 * returns a stale agent.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class WidgetAgent implements ContextualAttribute
{
    /** Request attribute holding the external id => agent memo. */
    private const string MEMO = 'widget_agent_memo';

    public static function resolve(self $attribute, Container $container, ?ReflectionParameter $parameter = null): ?Agent
    {
        $agent = self::from($container->make('request'));

        if (! $agent instanceof Agent && $parameter instanceof ReflectionParameter && ! $parameter->allowsNull()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $agent;
    }

    /**
     * The agent named by the request's X-AGENT-ID header, or null when the
     * header is missing or matches no agent.
     */
    public static function from(Request $request): ?Agent
    {
        $externalId = $request->header(WidgetHeaders::AGENT_ID);

        if (blank($externalId)) {
            return null;
        }

        /** @var array<string, Agent|null> $memo */
        $memo = $request->attributes->get(self::MEMO, []);

        if (! array_key_exists($externalId, $memo)) {
            $memo[$externalId] = Agent::query()->where('external_id', $externalId)->first();
            $request->attributes->set(self::MEMO, $memo);
        }

        return $memo[$externalId];
    }
}
