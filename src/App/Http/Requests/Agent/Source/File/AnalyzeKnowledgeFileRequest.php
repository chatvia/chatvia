<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\File;

use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Validates a file the owner wants measured (pages or minutes) before upload.
 * The check is by extension: exotic document formats share generic MIME types,
 * so a `mimes` rule cannot gate them.
 */
class AnalyzeKnowledgeFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'extensions:'.Arr::join(SourceFileTypes::supportedExtensions(), ','), 'max:'.SourceFileTypes::MAX_UPLOAD_KB],
        ];
    }
}
