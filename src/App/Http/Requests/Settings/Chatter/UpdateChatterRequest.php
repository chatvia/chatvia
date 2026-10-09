<?php

namespace App\Http\Requests\Settings\Chatter;

use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Workspace\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A chatter's access settings from the Members sheet: monthly message cap and
 * on/off switch.
 */
class UpdateChatterRequest extends FormRequest
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
            // Null clears the limit (unlimited); a positive integer sets the
            // monthly message cap.
            'messages_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'deactivated' => ['required', 'boolean'],
            'deactivation_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(User $chatter): UpdateChatterAccessData
    {
        $limit = $this->input('messages_limit');

        return new UpdateChatterAccessData(
            chatter: $chatter,
            messagesLimit: $limit !== null && $limit !== '' ? (int) $limit : null,
            deactivated: $this->boolean('deactivated'),
            deactivationReason: $this->input('deactivation_reason') ?: null,
        );
    }
}
