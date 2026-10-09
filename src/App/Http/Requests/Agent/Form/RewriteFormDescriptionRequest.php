<?php

namespace App\Http\Requests\Agent\Form;

use Domain\Form\Data\RewriteFormDescriptionData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ask the LLM to rewrite a form's "when to show this form" instruction. The
 * form does not have to be saved yet, so its name and type come along.
 */
class RewriteFormDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware (agent.permission) + the controller's update check gate access.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agent_instructions' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:255'],
            'form_type' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function toData(): RewriteFormDescriptionData
    {
        $validated = $this->validated();

        return new RewriteFormDescriptionData(
            agent_instructions: $validated['agent_instructions'] ?? null,
            name: $validated['name'] ?? null,
            form_type: $validated['form_type'] ?? null,
        );
    }
}
