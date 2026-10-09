<?php

namespace App\Http\Requests\Agent\Source\File;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates linking an existing file to an agent. Whether the caller may see
 * the file is checked by the file policy in the controller.
 */
class LinkSourceFileRequest extends FormRequest
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
            'file_id' => ['required', 'exists:source_files,id'],
        ];
    }
}
