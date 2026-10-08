<?php

namespace App\Http\Controllers\Chat\Transcription;

use App\Http\Requests\Chat\TranscribeAudioRequest;
use Domain\Conversation\Actions\Messages\TranscribeAudioAction;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ChatTranscriptionController
{
    public function __invoke(
        TranscribeAudioRequest $request,
        TranscribeAudioAction $transcribe,
    ): JsonResponse {
        try {
            $text = $transcribe->execute($request->file('audio'));
        } catch (TranscriptionNotConfiguredException $e) {
            // Not a failure to retry: this install has no speech-to-text service.
            return response()->json(['error' => $e->getMessage()], 503);
        } catch (Exception $e) {
            Log::error('Transcription failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Transcription failed. Please try again.'], 500);
        }

        return response()->json(['text' => $text]);
    }
}
