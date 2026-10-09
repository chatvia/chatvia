<?php

namespace App\Http\Chat;

use App\Http\Macros\RequestMacros;
use Attribute;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Http\Request;
use ReflectionParameter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The agent a hosted-chat request is for, named by the request's agent slug
 * (`?agent=`, a custom domain, the agent subdomain or the session — see
 * {@see RequestMacros}).
 *
 * Put it on a controller or FormRequest parameter to receive the agent:
 * `#[HostedChatAgent] Agent $agent`. A nullable parameter receives null when
 * the request names no known agent; a non-nullable one turns that into a 404.
 * Middleware and other code holding a request call {@see from()}.
 *
 * The lookup runs once per request: it is memoised on the request's attribute
 * bag, keyed by slug, so the chat middleware, the form request and the
 * controller share one query, a changed slug never returns a stale agent, and
 * a long-lived worker never serves one request's agent to the next.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class HostedChatAgent implements ContextualAttribute
{
    /** Request attribute holding the slug => agent memo. */
    private const string MEMO = 'hosted_chat_agent_memo';

    public static function resolve(self $attribute, Container $container, ?ReflectionParameter $parameter = null): ?Agent
    {
        $agent = self::from($container->make('request'));

        if ($agent === null && $parameter !== null && ! $parameter->allowsNull()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $agent;
    }

    /**
     * The agent the request is for, or null when it names none or the slug
     * matches no agent.
     */
    public static function from(Request $request): ?Agent
    {
        $slug = $request->agentSlug();

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        /** @var array<string, Agent|null> $memo */
        $memo = $request->attributes->get(self::MEMO, []);

        if (! array_key_exists($slug, $memo)) {
            $memo[$slug] = AgentLocator::resolveAgentFromSubdomain($slug);
            $request->attributes->set(self::MEMO, $memo);
        }

        return $memo[$slug];
    }
}
