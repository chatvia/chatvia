<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Channel\Services\ChannelLocale;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Illuminate\Support\Str;

/**
 * Resolves Telegram slash commands (/start, /reset, /help and the agent's own
 * custom commands) *before* the chat pipeline runs.
 *
 * Each command is either a **message** — sent verbatim, no LLM, nothing counted — or a
 * **prompt**, run through the agent pipeline like a normal turn (RAG + tools +
 * LLM, billed). Built-in commands are pre-seeded, editable rows: /start greets,
 * /reset always starts a new thread, /help auto-lists the commands when blank.
 *
 * On Telegram a "command" is text starting with `/` — Telegram auto-sends
 * `/start` the moment a user opens the bot, so this is what turns that first tap
 * into the agent's greeting rather than a literal "/start" sent to the LLM.
 *
 * Telegram-local on purpose: other channels don't share the `/command` model.
 */
class TelegramCommandRouter
{
    /** Built-in command names — always present, can't be deleted or renamed. */
    public const RESERVED = ['start', 'reset', 'help'];

    /**
     * Code defaults for the built-ins (English source text), overlaid with the
     * agent's stored edits. Translated into the agent's channel language when
     * used — see {@see builtinDefaults()}.
     */
    private const array BUILTIN_DEFAULTS = [
        'start' => ['description' => 'Start the conversation', 'type' => 'message', 'value' => 'Hello'],
        'reset' => ['description' => 'Start a new conversation', 'type' => 'message', 'value' => 'Started a new conversation. What would you like to talk about?'],
        'help' => ['description' => 'What this assistant can do', 'type' => 'message', 'value' => ''],
    ];

    public function __construct(
        private readonly TelegramChatResolver $resolver,
        private readonly TelegramClient $client,
    ) {}

    /**
     * Resolve $text to a command outcome — a canned message or a prompt to run —
     * or null to let the message fall through to the normal agent pipeline (a
     * plain message, or an unknown /command we'd rather let the LLM field).
     */
    public function handle(AgentTelegramBot $bot, TelegramChat $chat, string $text, TelegramSenderData $sender = new TelegramSenderData): ?TelegramCommandOutcome
    {
        $name = $this->parse($text);

        if ($name === null) {
            return null;
        }

        // /reset always rotates onto a fresh thread first, whatever its response is.
        if ($name === 'reset') {
            $this->resolver->startNewConversation($chat);
        }

        $commands = $this->effective($bot);

        if (! isset($commands[$name])) {
            return null; // unknown custom command → fall through to the agent
        }

        $entry = $commands[$name];
        $value = trim($entry['value']);

        // /help with no custom text auto-lists the available commands.
        if ($name === 'help' && $value === '') {
            return TelegramCommandOutcome::message($this->autoHelp($bot, $commands));
        }

        // Never let /start go silent.
        if ($name === 'start' && $value === '') {
            $value = ChannelLocale::text($bot->agent, 'Hello');
        }

        $value = $this->interpolate($bot, $value, $sender);

        return $entry['type'] === 'prompt'
            ? TelegramCommandOutcome::prompt($value)
            : TelegramCommandOutcome::message($value);
    }

    /**
     * Re-register the bot's "/" autocomplete menu (built-ins + custom commands).
     * Called on connect and whenever the commands change. Best-effort.
     */
    public function sync(AgentTelegramBot $bot): void
    {
        $menu = array_map(
            static fn (array $e): array => [
                'command' => $e['command'],
                'description' => $e['description'] !== '' ? $e['description'] : ucfirst($e['command']),
            ],
            array_values($this->effective($bot)),
        );

        $this->client->setMyCommands($bot->bot_token, $menu);
    }

    /**
     * The command list for the management UI: built-ins first (flagged), then the
     * agent's custom commands.
     *
     * @return list<array{command: string, description: string, type: string, value: string, builtin: bool}>
     */
    public function forEditing(AgentTelegramBot $bot): array
    {
        return array_map(
            fn (array $e): array => [...$e, 'builtin' => in_array($e['command'], self::RESERVED, true)],
            array_values($this->effective($bot)),
        );
    }

    /**
     * Built-in defaults overlaid with stored edits, then custom commands — keyed
     * by command name, built-ins first.
     *
     * @return array<string, array{command: string, description: string, type: string, value: string}>
     */
    private function effective(AgentTelegramBot $bot): array
    {
        $stored = [];
        foreach ($bot->commands ?? [] as $c) {
            if (isset($c['command'])) {
                $stored[$c['command']] = $c;
            }
        }

        $result = [];

        foreach ($this->builtinDefaults($bot) as $name => $default) {
            $entry = $stored[$name] ?? [];
            $result[$name] = [
                'command' => $name,
                'description' => (string) ($entry['description'] ?? $default['description']),
                'type' => ($entry['type'] ?? $default['type']) === 'prompt' ? 'prompt' : 'message',
                'value' => (string) ($entry['value'] ?? $default['value']),
            ];
        }

        foreach ($bot->commands ?? [] as $c) {
            $name = $c['command'] ?? null;
            if ($name === null || isset(self::BUILTIN_DEFAULTS[$name])) {
                continue;
            }
            $result[$name] = [
                'command' => (string) $name,
                'description' => (string) ($c['description'] ?? ''),
                'type' => ($c['type'] ?? 'message') === 'prompt' ? 'prompt' : 'message',
                'value' => (string) ($c['value'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * The built-in defaults with their texts in the agent's channel language.
     *
     * @return array<string, array{description: string, type: string, value: string}>
     */
    private function builtinDefaults(AgentTelegramBot $bot): array
    {
        $locale = ChannelLocale::for($bot->agent);

        return array_map(
            static fn (array $default): array => [
                ...$default,
                'description' => (string) __($default['description'], [], $locale),
                'value' => $default['value'] === '' ? '' : (string) __($default['value'], [], $locale),
            ],
            self::BUILTIN_DEFAULTS,
        );
    }

    /** Parse "/cmd@botname args" → "cmd" (lowercased), or null when it isn't a command. */
    private function parse(string $text): ?string
    {
        $text = ltrim($text);

        if (! str_starts_with($text, '/')) {
            return null;
        }

        // First whitespace-delimited token, minus the leading slash; in groups
        // Telegram appends "@botusername", which we strip.
        $token = strtok(substr($text, 1), " \n\t");

        // strtok returns false (never '') when the remainder is empty/all-whitespace.
        if ($token === false) {
            return null;
        }

        $command = strtolower(explode('@', $token, 2)[0]);

        return preg_match('/^[a-z0-9_]{1,32}$/', $command) === 1 ? $command : null;
    }

    /** Replace the {name} placeholder with the sender's first name (or a neutral fallback). */
    private function interpolate(AgentTelegramBot $bot, string $value, TelegramSenderData $sender): string
    {
        if (! str_contains($value, '{name}')) {
            return $value;
        }

        $name = Str::trim($sender->firstName ?? '');

        return str_replace('{name}', $name !== '' ? $name : ChannelLocale::text($bot->agent, 'there'), $value);
    }

    /**
     * @param  array<string, array{command: string, description: string, type: string, value: string}>  $commands
     */
    private function autoHelp(AgentTelegramBot $bot, array $commands): string
    {
        $lines = [ChannelLocale::text($bot->agent, 'Here is what I can do:'), ''];

        foreach ($commands as $name => $entry) {
            $description = trim($entry['description']);
            $lines[] = $description !== '' ? "/{$name} — {$description}" : "/{$name}";
        }

        $lines[] = '';
        $lines[] = ChannelLocale::text($bot->agent, 'Or just send a message — you can ask me anything.');

        return implode("\n", $lines);
    }
}
