<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Messages;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\SpeakMessageAction;
use Domain\Conversation\Data\Messages\MessageSpeechData;
use Domain\Conversation\Services\Speech\ReadAloudAvailability;
use Domain\Llm\Exceptions\SpeechNotConfiguredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Plays an agent reply aloud for the hosted-chat visitor it was written for
 * (the speaker button), as an audio file. A reply of another visitor, or an
 * agent that does not read aloud, is a 404; a failed reading answers with a
 * `message` the chat shows.
 */
class MessageSpeechController
{
    public function __invoke(
        #[HostedChatAgent] Agent $agent,
        string $conversationId,
        string $messageId,
        HostedChatSession $session,
        ReadAloudAvailability $availability,
        SpeakMessageAction $speak,
    ): StreamedResponse|JsonResponse {
        abort_unless($availability->isOnFor($agent), 404);

        try {
            $speech = $speak->execute($session->scope($agent, $conversationId), $messageId);
        } catch (SpeechNotConfiguredException) {
            return response()->json(['message' => __('Reading aloud is not available right now.')], 503);
        } catch (Throwable $e) {
            Log::error('Reading a reply aloud failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => __('The reply could not be read aloud. Please try again.')], 503);
        }

        abort_unless($speech instanceof MessageSpeechData, 404);

        return Storage::disk($speech->disk)->response($speech->path, 'reply.'.Str::afterLast($speech->path, '.'), [
            'Content-Type' => $speech->mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
