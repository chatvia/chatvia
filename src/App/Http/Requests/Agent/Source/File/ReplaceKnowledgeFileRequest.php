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
 * Validates the new version of a knowledge file: the same rules as an upload,
 * so it may not be a file the workspace already has (the same version again
 * included). The route enforces the file-training permission.
 */
class ReplaceKnowledgeFileRequest extends FormRequest
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
