<?php

namespace App\Http\Requests\Chat\Tools;

use Domain\Conversation\Data\Approval\ResolveToolApprovalData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'decisions.*' => ['required', 'string', 'in:approve,deny'],
        ];
    }

    public function toData(): ResolveToolApprovalData
    {
        /** @var array<string, string> $decisions */
        $decisions = $this->validated('decisions');

        return new ResolveToolApprovalData(decisions: $decisions);
    }
}
