<?php

namespace App\Http\Controllers\Widget\Sessions;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Widget\InitializeWidgetSessionRequest;
use Domain\Widget\Actions\InitializeWidgetSessionAction;
use Domain\Widget\Data\Session\WidgetSessionInitResultData;

/**
 * Widget boot: resumes or starts the visitor's session and conversation.
 */
class SessionInitController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        InitializeWidgetSessionRequest $request,
        InitializeWidgetSessionAction $initialize,
    ): WidgetSessionInitResultData {
        // On a protected agent the session is bound to the signed-in chatter;
        // an anonymous visitor on a public agent has none.
        return $initialize->execute(
            $this->widgetAgent(),
            $request->toData(),
            $this->widgetUser($request),
        );
    }
}
