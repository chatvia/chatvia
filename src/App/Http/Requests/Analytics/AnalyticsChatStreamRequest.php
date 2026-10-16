<?php

namespace App\Http\Requests\Analytics;

use Domain\Analytics\Data\Chat\AnalyticsChatRequestData;
use Domain\Analytics\Data\Chat\AnalyticsChatTurnData;
use Domain\Llm\Enums\ChatRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One "Ask your data" question plus the earlier turns the browser replays.
 */
class AnalyticsChatStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Owner-only: the analytics chat exposes whole-account numbers (incl.
        // identified users), so it's gated to the workspace owner.
        return $this->user()?->isWorkspaceOwner() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array', 'max:40'],
            'history.*.role' => ['required', 'string', Rule::enum(ChatRole::class)->only([ChatRole::User, ChatRole::Assistant])],
            'history.*.content' => ['required', 'string', 'max:8000'],
            'user_message_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toData(): AnalyticsChatRequestData
    {
        return new AnalyticsChatRequestData(
            message: (string) $this->validated('message'),
            history: array_values(array_map(
                static fn (array $turn): AnalyticsChatTurnData => new AnalyticsChatTurnData($turn['role'], $turn['content']),
                $this->validated('history') ?? [],
            )),
            userMessageId: $this->validated('user_message_id'),
        );
    }
}
