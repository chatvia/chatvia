<?php

namespace App\Http\Requests\Analytics;

use Domain\Analytics\Data\Chat\AnalyticsChatRequestData;
use Illuminate\Foundation\Http\FormRequest;

class AnalyticsChatStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Owner-only: the analytics chat exposes whole-account numbers (incl.
        // identified users), so it's gated to the workspace owner.
        return $this->user()?->isWorkspace() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array', 'max:40'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:8000'],
            'user_message_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toData(): AnalyticsChatRequestData
    {
        return new AnalyticsChatRequestData(
            message: (string) $this->validated('message'),
            history: array_map(
                static fn (array $turn): array => ['role' => $turn['role'], 'content' => $turn['content']],
                $this->validated('history') ?? [],
            ),
            userMessageId: $this->validated('user_message_id'),
        );
    }
}
