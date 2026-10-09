<?php

namespace App\Http\Requests\Agent\Source\File;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a file the owner wants measured (pages or minutes) before upload.
 * The check is by extension: exotic document formats share generic MIME types,
 * so a `mimes` rule cannot gate them. 51200 KB is the document parser's limit.
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
            'file' => ['required', 'file', 'extensions:'.implode(',', ocr_supported_extensions()), 'max:51200'],
        ];
    }
}
