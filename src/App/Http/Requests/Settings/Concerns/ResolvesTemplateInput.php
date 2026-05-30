<?php

namespace App\Http\Requests\Settings\Concerns;

/**
 * Shared validation rules + request→DTO mapping for the template create/update
 * requests. The only difference between the two is the `name` uniqueness rule
 * (update excludes the current record), so that lives in each request.
 *
 * Per-surface configurations (`hosted_chat`, `widget`, `bar`) are accepted as
 * nested arrays. Detailed per-surface validation lives in the Setup/Template
 * editor refactor; for now we accept any array shape so the editor can submit
 * partial drafts and the action persists them verbatim into the surface JSON
 * columns.
 */
trait ResolvesTemplateInput
{
    /**
     * Every rule except `name` (which differs between create and update).
     *
     * @return array<string, mixed>
     */
    protected function commonTemplateRules(): array
    {
        return [
            'prompt_id' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->user()->prompts()->where('external_id', $value)->exists()) {
                        $fail('The selected prompt does not exist on your account.');
                    }
                },
            ],
            'is_public' => ['boolean'],

            'hosted_chat' => ['nullable', 'array'],
            'widget' => ['nullable', 'array'],
            'bar' => ['nullable', 'array'],
        ];
    }

    /**
     * Resolve request input into the named arguments shared by both template
     * DTOs (keys match the DTO constructor parameter names).
     *
     * @return array<string, mixed>
     */
    protected function templateAttributes(): array
    {
        $promptId = $this->filled('prompt_id')
            ? $this->user()->prompts()->where('external_id', $this->input('prompt_id'))->value('id')
            : null;

        return [
            'name' => $this->string('name')->toString(),
            'agentPromptId' => $promptId,
            'isPublic' => $this->boolean('is_public'),
            'hostedChatConfig' => $this->input('hosted_chat'),
            'widgetConfig' => $this->input('widget'),
            'barConfig' => $this->input('bar'),
        ];
    }
}
