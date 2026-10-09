<?php

namespace App\Http\Requests\Chat\Tools;

use Domain\Conversation\Data\Approval\ResolveToolApprovalData;
use Domain\Conversation\Enums\ToolApprovalDecision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the approve / deny decisions a chatter posts for a paused turn's
 * gated tool calls. Ownership of the checkpoint is checked by the controller.
 */
class ResolveToolApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*' => ['required', 'string', Rule::enum(ToolApprovalDecision::class)],
        ];
    }

    public function toData(): ResolveToolApprovalData
    {
        /** @var array<string, string> $decisions */
        $decisions = $this->validated('decisions');

        return new ResolveToolApprovalData(
            decisions: array_map(ToolApprovalDecision::from(...), $decisions),
        );
    }
}
