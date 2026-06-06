<?php

namespace App\Http\Requests\Settings\Member;

use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Workspace\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The workspace boundary + permission gate is enforced in the controller
        // (abort_unless 404), matching the other member endpoints.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Null clears the budget (unlimited — only the workspace pool then
            // gates); a positive integer sets the member's monthly token budget.
            'tokens_limit' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'deactivated' => ['required', 'boolean'],
            'deactivation_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(User $chatter): UpdateChatterAccessData
    {
        $tokens = $this->input('tokens_limit');

        return new UpdateChatterAccessData(
            chatter: $chatter,
            tokensLimit: $tokens !== null && $tokens !== '' ? (int) $tokens : null,
            deactivated: $this->boolean('deactivated'),
            deactivationReason: $this->input('deactivation_reason') ?: null,
        );
    }
}
