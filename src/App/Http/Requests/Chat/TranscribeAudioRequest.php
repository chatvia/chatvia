<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Domain\Conversation\Enums\VoiceNoteFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

/**
 * Validates a voice note sent from the hosted-chat or widget composer for
 * transcription. Anyone who can chat may send one, and it goes to ffmpeg, so
 * only the audio formats the browsers' recorders write are taken
 * ({@see VoiceNoteFormat}): by detected type and by file name.
 */
class TranscribeAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'max:25600',
                'mimetypes:'.Arr::join(VoiceNoteFormat::allMimeTypes(), ','),
                'extensions:'.Arr::join(VoiceNoteFormat::allExtensions(), ','),
            ],
        ];
    }

    /** The validated recording. */
    public function audio(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('audio');
    }

    /** The recording's format, from its detected type (validation already limited it to these). */
    public function voiceNoteFormat(): VoiceNoteFormat
    {
        return VoiceNoteFormat::fromMimeType($this->audio()->getMimeType()) ?? abort(422);
    }
}
