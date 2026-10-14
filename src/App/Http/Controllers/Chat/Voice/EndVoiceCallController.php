<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Voice;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\EndVoiceCallAction;
use Illuminate\Http\Response;

/**
 * Hangs up the hosted-chat visitor's voice call (the End button). A call of
 * another visitor is a 404.
 */
readonly class EndVoiceCallController
{
    public function __construct(
        private HostedChatSession $session,
    ) {}

    public function __invoke(string $call, #[HostedChatAgent] Agent $agent, EndVoiceCallAction $end): Response
    {
        $sessionId = $this->session->find($agent);

        abort_if($sessionId === null, Response::HTTP_NOT_FOUND);

        $end->execute($agent, $sessionId, $call);

        return response()->noContent();
    }
}
