<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\File;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'type' => ['required', Rule::in(ListSourceFilesRequest::FILE_TYPES)],
        ];
    }

    public function fileType(): string
    {
        return (string) $this->validated('type');
    }
}
