<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget\Voice;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\EndVoiceCallAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Hangs up the widget visitor's voice call (the End button). A call of
 * another session is a 404.
 */
readonly class EndVoiceCallController
{
    use InteractsWithWidgetRequest;

    public function __invoke(Request $request, string $call, #[WidgetAgent] Agent $agent, EndVoiceCallAction $end): Response
    {
        $sessionId = $this->widgetSessionId($request);

        abort_if($sessionId === null, Response::HTTP_NOT_FOUND);

        $end->execute($agent, $sessionId, $call);

        return response()->noContent();
    }
}
