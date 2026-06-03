<?php

namespace App\Http\Controllers\Widget\Threads;

use Domain\Widget\Actions\ListWidgetThreadsAction;
use Domain\Widget\Data\Thread\WidgetThreadsResultData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;

class ThreadController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ListWidgetThreadsAction $list): WidgetThreadsResultData
    {
        if (! $widgetVerification->verifyByAgentExternalId($request->header('X-AGENT-ID'), $request)) {
            return new WidgetThreadsResultData(threads: []);
        }

        return $list->execute(current_agent_from_widget(), (string) $request->header('X-SESSION-ID'));
    }
}
