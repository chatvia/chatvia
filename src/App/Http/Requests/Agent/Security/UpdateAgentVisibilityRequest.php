<?php

namespace App\Http\Requests\Agent\Security;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Makes an agent public (anyone can chat) or protected (sign-in tokens only)
 * from its Security page. Authorization is the route's
 * `agent.permission:agent.setup` middleware.
 */
class UpdateAgentVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'is_public' => ['required', 'boolean'],
        ];
    }

    public function isPublic(): bool
    {
        return $this->boolean('is_public');
    }
}
