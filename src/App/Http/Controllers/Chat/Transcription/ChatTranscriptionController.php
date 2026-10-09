<?php

namespace App\Http\Controllers\Chat\Transcription;

use App\Http\Requests\Chat\TranscribeAudioRequest;
use Domain\Conversation\Actions\Messages\TranscribeAudioAction;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Turns a voice note from the hosted-chat composer into text.
 */
class ChatTranscriptionController
{
    public function __invoke(
        TranscribeAudioRequest $request,
        TranscribeAudioAction $transcribe,
    ): JsonResponse {
        $audio = $request->file('audio');

        try {
            $text = $transcribe->execute((string) $audio->getRealPath(), $audio->getClientOriginalExtension());
        } catch (TranscriptionNotConfiguredException $e) {
            // Not a failure to retry: this install has no speech-to-text service.
            return response()->json(['error' => $e->getMessage()], 503);
        } catch (Exception $e) {
            Log::error('Transcription failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => __('Transcription failed. Please try again.')], 500);
        }

        return response()->json(['text' => $text]);
    }
}
