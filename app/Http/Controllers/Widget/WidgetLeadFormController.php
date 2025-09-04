<?php

namespace App\Http\Controllers\Widget;

use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetLeadFormController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification)
    {
        $agentId = $request->header('x-agent-id');

        $status = $widgetVerification->verifyByAgentExternalId($agentId, $request);

        abort_if(! $status, 403);

        return [
            'name',
            'email',
            'phone',
            'company',
            'location',
        ];
    }
}
