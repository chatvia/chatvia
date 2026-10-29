<?php

declare(strict_types=1);

namespace App\Http\Middleware\Chat;

use App\Admin\Issue\Services\IssueReporter;
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

        if (! $agent instanceof Agent) {
            return Inertia::render('chat/pages/AgentNotFound');
        }

        HostedChatAgent::remember($request, $agent);
        // Jobs this request queues carry the agent and its workspace (an
        // issue it meets names both).
        Context::addHidden('workspace_id', $agent->workspace_id);
        Context::addHidden(IssueReporter::AGENT_CONTEXT, $agent->id);
        URL::defaults(['agentSlug' => $slug]);
        $request->route()?->forgetParameter('agentSlug');

        return $next($request);
    }
}
