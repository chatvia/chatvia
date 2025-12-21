<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Services\Infrastructure\LLM\Operations\Transcription;
use App\Services\Media\AudioCompressor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatTranscriptionController extends Controller
{
    public function __invoke(Request $request, AudioCompressor $audioCompressor): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:25600'], // 25MB max (Whisper limit)
        ]);

        $file = $request->file('audio');
        $tempPath = temp_dir('/transcription').'/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        $file->move(dirname($tempPath), basename($tempPath));

        try {
            // Compress audio for optimal Whisper processing
            $compressedPath = $audioCompressor->process($tempPath);

            // Transcribe using OpenAI Whisper
            $transcription = app(Transcription::class)->transcribe($tempPath);

            // Clean up temp files
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            //
            if (file_exists($compressedPath)) {
                unlink($compressedPath);
            }

            return response()->json([
                'text' => $transcription,
            ]);
        } catch (\Exception $e) {
            Log::error('Transcription failed', [
                'error' => $e->getMessage(),
            ]);

            // Clean up on error
            //            if (file_exists($tempPath)) {
            //                unlink($tempPath);
            //            }

            return response()->json([
                'error' => 'Transcription failed. Please try again.',
            ], 500);
        }
    }
}
