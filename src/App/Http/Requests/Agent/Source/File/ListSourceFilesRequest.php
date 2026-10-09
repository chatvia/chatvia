<?php

namespace App\Http\Requests\Agent\Source\File;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters the agent's file list by kind (`pdf`, `word`, `audio`, `video`).
 * Without a kind it lists every document (everything except audio and video).
 */
class ListSourceFilesRequest extends FormRequest
{
    /** The kinds a caller may filter by. */
    public const array FILE_TYPES = ['pdf', 'word', 'audio', 'video'];

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
            'file_type' => ['nullable', 'in:'.implode(',', self::FILE_TYPES)],
        ];
    }

    public function fileType(): ?string
    {
        return $this->validated('file_type');
    }
}
