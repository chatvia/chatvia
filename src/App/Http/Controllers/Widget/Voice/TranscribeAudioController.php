<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget\Voice;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Chat\TranscribeAudioRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\TranscribeAudioAction;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Domain\Widget\Services\WidgetVerification;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Turns a voice note from the widget composer's mic into text, like the
 * hosted chat's transcription. Speech to text costs the agent's workspace,
 * so it needs a session of this agent and the owner's "Allow voice input".
 * The widget shows its own text for a failure.
 */
readonly class TranscribeAudioController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        TranscribeAudioRequest $request,
        #[WidgetAgent] Agent $agent,
        WidgetVerification $verification,
        TranscribeAudioAction $transcribe,
    ): JsonResponse {
        // The session must belong to THIS agent and to the signed-in viewer.
        abort_if(! $verification->verifySessionExternalId($this->widgetSessionId($request), $agent, $this->widgetViewer($request)), JsonResponse::HTTP_FORBIDDEN, 'Invalid session id');
        abort_if(! (bool) ($agent->settings('appearance')->get('allow_voice_input') ?? true), JsonResponse::HTTP_FORBIDDEN, 'Voice input is off for this agent');

        try {
            $text = $transcribe->execute((string) $request->audio()->getRealPath(), $request->voiceNoteFormat());
        } catch (TranscriptionNotConfiguredException) {
            // Not a failure to retry: this install has no speech-to-text service.
            return response()->json(['message' => 'Voice input is not available'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        } catch (Exception $e) {
            Log::error('Widget transcription failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Transcription failed'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['text' => $text]);
    }
}
