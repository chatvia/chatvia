<?php

namespace App\Http\Controllers\Widget;

use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetLeadFormController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification)
    {
        $agentId = $request->header('X-AGENT-ID');

        $status = $widgetVerification->verifyByAgentExternalId($agentId, $request);

        abort_if(! $status, 403);

        return current_agent_from_widget()->settings('leads');
    }
}
