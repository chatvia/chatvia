<?php

namespace App\Http\Controllers\Chat\Transcription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\TranscribeAudioRequest;
use Domain\Conversation\Actions\Messages\TranscribeAudioAction;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ChatTranscriptionController extends Controller
{
    public function __invoke(
        TranscribeAudioRequest $request,
        TranscribeAudioAction $transcribe,
    ): JsonResponse {
        try {
            $text = $transcribe->execute($request->file('audio'));
        } catch (Exception $e) {
            Log::error('Transcription failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Transcription failed. Please try again.'], 500);
        }

        return response()->json(['text' => $text]);
    }
}
