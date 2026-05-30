<?php

namespace App\Http\Requests\Api\V1\Sources;

use Illuminate\Foundation\Http\FormRequest;

class UploadSourceFileRequest extends FormRequest
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
            'file' => ['required', 'file'],
        ];
    }
}
