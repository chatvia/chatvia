<?php

namespace App\Http\Chat;

use App\Http\Middleware\Chat\ResolveHostedChatAgent;
use Attribute;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Http\Request;
use ReflectionParameter;
use Symfony\Component\HttpFoundation\Response;

/**
 * The agent a hosted-chat request is for: the one named by the
 * `/chat/{agentSlug}` path segment, found once per request by
 * {@see ResolveHostedChatAgent} and kept on the request.
 *
 * Put it on a controller or FormRequest parameter to receive the agent:
 * `#[HostedChatAgent] Agent $agent`. A nullable parameter receives null when
 * the request names no known agent; a non-nullable one turns that into a 404.
 * Middleware and other code holding a request call {@see from()}.
 *
 * The agent lives on the request's attribute bag, so a long-lived worker never
 * serves one request's agent to the next.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class HostedChatAgent implements ContextualAttribute
{
    /** Request attribute holding the resolved agent. */
    private const string ATTRIBUTE = 'hosted_chat_agent';

    public static function resolve(self $attribute, Container $container, ?ReflectionParameter $parameter = null): ?Agent
    {
        $agent = self::from($container->make('request'));

        if (! $agent instanceof Agent && $parameter instanceof ReflectionParameter && ! $parameter->allowsNull()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $agent;
    }

    /**
     * The agent the request is for, or null outside the hosted chat.
     */
    public static function from(Request $request): ?Agent
    {
        $agent = $request->attributes->get(self::ATTRIBUTE);

        return $agent instanceof Agent ? $agent : null;
    }

    /** Keeps the agent the request's path names, for {@see from()}. */
    public static function remember(Request $request, Agent $agent): void
    {
        $request->attributes->set(self::ATTRIBUTE, $agent);
    }
}
