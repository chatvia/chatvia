<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\File;

use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the files to remove from an agent in one call: at most
 * {@see MAX_FILES}, all of them files the agent in the route uses.
 */
class BulkDestroySourceFilesRequest extends FormRequest
{
    /** Most files one call may remove, so one request stays short; the dashboard sends a larger selection in several calls. */
    public const int MAX_FILES = 100;

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
            'file_ids' => ['required', 'array', 'max:'.self::MAX_FILES],
            'file_ids.*' => ['required', 'integer'],
        ];
    }

    /**
     * Every id must be a file of the agent in the route, checked with one
     * count instead of one lookup per id. The agent's workspace is checked by
     * the route middleware.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = $this->uniqueIds($this->input('file_ids'));
                $agent = $this->route('agent');
                $used = $agent instanceof Agent ? $agent->files()->whereIn('source_files.id', $ids)->count() : 0;

                if ($used !== count($ids)) {
                    $validator->errors()->add('file_ids', __('validation.exists', ['attribute' => $validator->getDisplayableAttribute('file_ids')]));
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function fileIds(): array
    {
        return $this->uniqueIds($this->validated('file_ids'));
    }

    /**
     * @return list<int>
     */
    private function uniqueIds(mixed $ids): array
    {
        return collect((array) $ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
