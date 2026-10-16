<?php

namespace App\Http\Requests\Agent\Source\File;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters the agent's file list by kind (`pdf`, `word`, `audio`, `video`) and
 * stage: `uploaded` lists the files waiting to be trained, `trained` (the
 * default) the others. Without a kind it lists every document (everything
 * except audio and video). `page` picks the page of the list.
 */
class ListSourceFilesRequest extends FormRequest
{
    /** The kinds a caller may filter by. */
    public const array FILE_TYPES = ['pdf', 'word', 'audio', 'video'];

    /** The stages a caller may filter by. */
    public const array STAGES = ['uploaded', 'trained'];

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
            'file_type' => ['nullable', Rule::in(self::FILE_TYPES)],
            'stage' => ['nullable', Rule::in(self::STAGES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function fileType(): ?string
    {
        return $this->validated('file_type');
    }

    /** Whether the list is of the files waiting to be trained. */
    public function waitingForTraining(): bool
    {
        return $this->validated('stage') === 'uploaded';
    }
}
