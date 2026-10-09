<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new title for one of the hosted-chat visitor's conversations.
 * Which conversation may be renamed is checked by the controller.
 */
class RenameConversationRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    public function title(): string
    {
        return $this->string('title')->toString();
    }
}
