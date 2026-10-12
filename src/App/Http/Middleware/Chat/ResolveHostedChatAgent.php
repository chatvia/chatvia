<?php

declare(strict_types=1);

namespace App\Http\Middleware\Chat;

use App\Http\Chat\HostedChatAgent;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * First middleware of every hosted chat route (alias `chat.agent`): finds the
 * agent named by the `/chat/{agentSlug}` path segment (its slug or external
 * id) and keeps it on the request for {@see HostedChatAgent}.
 *
 *  - An unknown agent shows the not-found page.
 *  - On an agent's custom domain, only that agent is served there.
 *  - URL::defaults() fills in `agentSlug`, so route('chat.home') and the other
 *    chat routes need no agent argument.
 *  - The segment is then dropped from the route parameters: controllers take
 *    route parameters by position (`string $conversationId`), and get the
 *    agent through #[HostedChatAgent] instead.
 */
final class ResolveHostedChatAgent
{
    public function handle(Request $request, Closure $next): Response|InertiaResponse
    {
        $slug = (string) $request->route('agentSlug');
        $agent = AgentLocator::resolveAgentBySlug($slug);

        if (! $agent instanceof Agent || ! $this->servedOnThisHost($request, $agent)) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        HostedChatAgent::remember($request, $agent);
        // Jobs this request queues carry the agent's workspace.
        Context::addHidden('workspace_id', $agent->workspace_id);
        URL::defaults(['agentSlug' => $slug]);
        $request->route()?->forgetParameter('agentSlug');

        return $next($request);
    }

    /**
     * A custom domain belongs to one agent, so it never serves another
     * agent's chat. Any other host (the app's own, or an address that is not
     * a custom domain) serves every agent.
     */
    private function servedOnThisHost(Request $request, Agent $agent): bool
    {
        $host = $request->getHost();

        if ($host === parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return true;
        }

        $owner = AgentLocator::resolveByHost($host);

        return ! $owner instanceof Agent || $owner->is($agent);
    }
}
