<?php

namespace App\Http\Requests\Agent\Source\File;

use App\Http\Requests\Concerns\ReadsUploadedSourceFile;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a knowledge-file upload from the agent's Files page. The file type
 * is checked by the upload action (document formats by extension, audio and
 * video by content); access is checked by the route middleware.
 */
class UploadKnowledgeFileRequest extends FormRequest
{
    use ReadsUploadedSourceFile;

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
            'file' => ['required', 'file'],
        ];
    }

    public function toData(): UploadedSourceFileData
    {
        return $this->uploadedSourceFile();
    }
}
