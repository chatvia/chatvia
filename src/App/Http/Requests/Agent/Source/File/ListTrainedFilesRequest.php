<?php

namespace App\Http\Requests\Agent\Source\File;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lists the workspace's trained files of one kind that the agent does not use
 * yet (the "add an existing file" picker).
 */
class ListTrainedFilesRequest extends FormRequest
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
            'type' => ['required', 'in:'.implode(',', ListSourceFilesRequest::FILE_TYPES)],
        ];
    }

    public function fileType(): string
    {
        return (string) $this->validated('type');
    }
}
