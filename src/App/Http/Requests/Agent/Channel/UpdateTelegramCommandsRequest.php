<?php

namespace App\Http\Requests\Agent\Channel;

use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an agent's custom Telegram commands. The route already enforces
 * `agent.permission:agent.setup`, so authorize() defers to it. Command names
 * follow Telegram's rules (lowercase letters, digits, underscores; ≤32 chars)
 * and can't shadow the built-ins (/start, /reset, /help).
 */
class UpdateTelegramCommandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise before validation: drop the leading slash a user might type,
     * lowercase the command, and trim the labels.
     */
    protected function prepareForValidation(): void
    {
        $commands = array_map(
            static fn (array $c): array => [
                'command' => ltrim(strtolower(trim((string) ($c['command'] ?? ''))), '/'),
                'description' => trim((string) ($c['description'] ?? '')),
                'reply' => trim((string) ($c['reply'] ?? '')),
            ],
            (array) $this->input('commands', []),
        );

        $this->merge(['commands' => $commands]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commands' => ['present', 'array', 'max:50'],
            'commands.*.command' => [
                'required',
                'string',
                'regex:/^[a-z0-9_]{1,32}$/',
                Rule::notIn(TelegramCommandRouter::RESERVED),
                'distinct:ignore_case',
            ],
            'commands.*.description' => ['required', 'string', 'max:256'],
            'commands.*.reply' => ['required', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'commands.*.command.regex' => 'Use lowercase letters, digits, and underscores only (max 32 characters).',
            'commands.*.command.not_in' => 'start, reset, and help are built-in commands and can’t be redefined.',
            'commands.*.command.distinct' => 'Each command can only be defined once.',
        ];
    }

    /**
     * @return list<array{command: string, description: string, reply: string}>
     */
    public function commands(): array
    {
        /** @var list<array{command: string, description: string, reply: string}> $commands */
        $commands = (array) $this->input('commands', []);

        return array_values($commands);
    }
}
