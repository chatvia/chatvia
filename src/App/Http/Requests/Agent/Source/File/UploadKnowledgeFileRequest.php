<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\File;

use App\Http\Requests\Concerns\ReadsUploadedSourceFile;
use App\Http\Rules\NotUploadedBefore;
use App\Http\Rules\UnlockedPdf;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a knowledge-file upload from the agent's Files page: it opens
 * without a password, and the workspace has not uploaded it before. The file
 * type is checked by the upload action (document formats by extension, audio
 * and video by content); access is checked by the route middleware.
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
            'file' => ['required', 'file', 'max:'.SourceFileTypes::MAX_UPLOAD_KB, new UnlockedPdf, new NotUploadedBefore($this->user()->workspace())],
        ];
    }

    public function toData(): UploadedSourceFileData
    {
        return $this->uploadedSourceFile();
    }
}
