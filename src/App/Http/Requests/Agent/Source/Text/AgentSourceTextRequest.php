<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\Text;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the text of a new or changed text source (at least 500
 * characters). Access to the agent is checked by the route middleware.
 */
class AgentSourceTextRequest extends FormRequest
{
    /**
     * Access to the agent is checked by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:500'],
        ];
    }

    public function textContent(): string
    {
        return (string) $this->validated('content');
    }
}
