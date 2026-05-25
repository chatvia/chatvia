<?php

namespace App\Http\Controllers\Widget;

use App\Widget\WidgetVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WidgetLeadFormController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification): Collection
    {
        $agentId = $request->header('X-AGENT-ID');

        $status = $widgetVerification->verifyByAgentExternalId($agentId, $request);

        abort_if(! $status, 403);

        return current_agent_from_widget()->settings('leads');
    }
}
