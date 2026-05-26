<?php

namespace App\Http\Controllers\Widget;

use App\Http\Requests\Widget\CollectVisitorLeadRequest;
use App\Widget\WidgetVerification;
use Domain\Agents\Actions\CollectVisitorLeadAction;
use Illuminate\Http\Response;

class WidgetLeadCollectController
{
    public function __invoke(
        CollectVisitorLeadRequest $request,
        WidgetVerification $widgetVerification,
        CollectVisitorLeadAction $collectLead,
    ): Response {
        $agentId = $request->header('X-AGENT-ID');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response('Could not verify the agent', 403);
        }

        $collectLead->execute($request->toData(current_agent_from_widget()));

        return response(status: 201);
    }
}
