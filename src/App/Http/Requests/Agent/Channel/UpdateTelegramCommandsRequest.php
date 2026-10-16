<?php

namespace App\Http\Requests\Agent\Channel;

use Domain\Channel\Telegram\Data\TelegramCommandData;
use Domain\Channel\Telegram\Data\UpdateTelegramCommandsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an agent's Telegram commands — built-in overrides (/start, /reset,
 * /help) plus custom commands. The route already enforces
 * `agent.permission:agent.channels`, so authorize() defers to it.
 *
 * Each command is a {command, description, type, value}: `type` is "message"
 * (canned text sent as-is) or "prompt" (run through the agent). Command names
 * follow Telegram's rules (lowercase letters, digits, underscores; ≤32 chars).
 * `/help` may have a blank value (it then auto-lists the commands); every other
 * command needs a value.
 */
class UpdateTelegramCommandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise before validation: drop a leading slash, lowercase the command,
     * default the type, and trim the labels.
     */
    protected function prepareForValidation(): void
    {
        $commands = array_map(
            static fn (array $c): array => [
                'command' => ltrim(strtolower(trim((string) ($c['command'] ?? ''))), '/'),
                'description' => trim((string) ($c['description'] ?? '')),
                'type' => ($c['type'] ?? 'message') === 'prompt' ? 'prompt' : 'message',
                'value' => trim((string) ($c['value'] ?? '')),
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
            'commands' => ['present', 'array', 'max:60'],
            'commands.*.command' => ['required', 'string', 'regex:/^[a-z0-9_]{1,32}$/', 'distinct:ignore_case'],
            'commands.*.description' => ['required', 'string', 'max:256'],
            'commands.*.type' => ['required', Rule::in(['message', 'prompt'])],
            'commands.*.value' => ['nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * Every command needs a value except /help, which auto-lists the commands
     * when left blank.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('commands', []) as $i => $command) {
                    $isHelp = ($command['command'] ?? '') === 'help';

                    if (! $isHelp && blank($command['value'] ?? '')) {
                        $validator->errors()->add("commands.{$i}.value", __('Add the message or prompt for this command.'));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'commands.*.command.regex' => __('Use lowercase letters, digits, and underscores only (max 32 characters).'),
            'commands.*.command.distinct' => __('Each command can only be defined once.'),
        ];
    }

    public function toData(): UpdateTelegramCommandsData
    {
        /** @var list<array{command: string, description: string, type: string, value: string}> $commands */
        $commands = array_values((array) $this->input('commands', []));

        return new UpdateTelegramCommandsData(
            commands: array_map(static fn (array $command): TelegramCommandData => new TelegramCommandData(
                command: $command['command'],
                description: $command['description'],
                type: $command['type'],
                value: $command['value'],
            ), $commands),
        );
    }
}
