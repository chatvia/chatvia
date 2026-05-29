<?php

namespace App\Http\Controllers\Widget;

use App\Widget\WidgetVerification;
use Domain\Chat\Services\ChatStreamService;
use Domain\Chat\Services\Streaming\StreamRequestDataFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class WidgetStreamController
{
    public function __construct(
        private ChatStreamService $chatStreamService,
        private StreamRequestDataFactory $factory,
    ) {}

    public function __invoke(Request $request, WidgetVerification $verification): StreamedResponse
    {
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');

        $session = $verification->verifySessionExternalId($sessionId);

        abort_if(! $session, 403, 'Invalid session id');

        $agent = current_agent_from_widget();

        $leadCollected = $agent
            ->leads()
            ->with('conversation.session')
            ->whereHas('conversation', function ($query) use ($session): void {
                $query->where('chat_session_id', $session->id);
            })
            ->exists();

        $referer = $request->header('referer') ?: $request->server('HTTP_REFERER');
        $isPlayground = str_starts_with((string) $referer, route('agents.widget.preview', ['agent' => $agent]));

        return $this->chatStreamService->stream(
            $this->factory->forWidget(
                $request,
                $agent,
                $sessionId,
                $threadId,
                $leadCollected,
                $isPlayground,
            ),
        );
    }
}
