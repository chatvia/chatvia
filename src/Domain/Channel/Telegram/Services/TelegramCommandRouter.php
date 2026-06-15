<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;

/**
 * Answers Telegram slash commands (/start, /reset, /help and the agent's own
 * custom commands) *before* the chat pipeline runs. Built-in commands are
 * code-defined and free — no LLM, no billing; custom commands are canned-text
 * replies the workspace configures on the Channels page.
 *
 * On Telegram a "command" is text starting with `/` — Telegram auto-sends
 * `/start` the moment a user opens the bot, so this is what turns that first
 * tap into the agent's greeting rather than a literal "/start" sent to the LLM.
 *
 * This is Telegram-local on purpose: other channels (Slack slash commands, the
 * Meta channels) don't share Telegram's `/command` model, so there's no shared
 * cross-channel abstraction here.
 */
class TelegramCommandRouter
{
    /** Built-in command names the agent can't redefine. */
    public const RESERVED = ['start', 'reset', 'help'];

    public function __construct(
        private readonly TelegramChatResolver $resolver,
        private readonly TelegramClient $client,
    ) {}

    /**
     * If $text is a slash command, handle it and return the reply to send back.
     * Returns null to let the message fall through to the normal agent pipeline —
     * that covers both a plain message and an unknown /command (we'd rather let
     * the LLM field "/menu" than bounce an "unknown command" error).
     *
     * @param  array{first_name?: string|null, username?: string|null}  $from
     */
    public function handle(AgentTelegramBot $bot, TelegramChat $chat, string $text, array $from = []): ?string
    {
        $command = $this->parse($text);

        if ($command === null) {
            return null;
        }

        return match ($command) {
            'start' => $this->greeting($bot, $from),
            'reset' => $this->reset($chat),
            'help' => $this->help($bot),
            default => $this->custom($bot, $command),
        };
    }

    /**
     * Re-register the bot's command menu (the "/" autocomplete list Telegram shows
     * users) with the built-ins followed by the agent's custom commands. Called on
     * connect and whenever the custom commands change. Best-effort (logs on failure).
     */
    public function sync(AgentTelegramBot $bot): void
    {
        $this->client->setMyCommands($bot->bot_token, $this->menu($bot));
    }

    /**
     * The full command menu for {@see sync()}: built-ins first, then custom commands.
     *
     * @return list<array{command: string, description: string}>
     */
    public function menu(AgentTelegramBot $bot): array
    {
        $builtins = [
            ['command' => 'start', 'description' => 'Start the conversation'],
            ['command' => 'reset', 'description' => 'Start a new conversation'],
            ['command' => 'help', 'description' => 'What this assistant can do'],
        ];

        $custom = array_map(
            static fn (array $c): array => ['command' => $c['command'], 'description' => $c['description']],
            $bot->custom_commands ?? [],
        );

        return [...$builtins, ...$custom];
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

        // strtok skips leading delimiters and returns false (never '') when the
        // remainder is empty or all-whitespace — e.g. a bare "/" or "/   ".
        if ($token === false) {
            return null;
        }

        $command = strtolower(explode('@', $token, 2)[0]);

        return preg_match('/^[a-z0-9_]{1,32}$/', $command) === 1 ? $command : null;
    }

    /**
     * /start → the agent's configured welcome message (the same one the web widget
     * shows), greeted with the sender's first name when Telegram gives us one.
     *
     * @param  array{first_name?: string|null, username?: string|null}  $from
     */
    private function greeting(AgentTelegramBot $bot, array $from): string
    {
        $welcome = trim((string) $bot->agent->settings('appearance')['welcome_message']);

        if ($welcome === '') {
            $welcome = 'Hi! How can I help you today?';
        }

        $name = trim((string) ($from['first_name'] ?? ''));

        return $name !== '' ? "👋 {$name}\n\n{$welcome}" : $welcome;
    }

    /** /reset → a fresh conversation thread under the same Session. */
    private function reset(TelegramChat $chat): string
    {
        $this->resolver->startNewConversation($chat);

        return 'Started a new conversation. What would you like to talk about?';
    }

    /** /help → a self-maintaining list of every available command. */
    private function help(AgentTelegramBot $bot): string
    {
        $lines = [
            'Here is what I can do:',
            '',
            '/start — restart and see the welcome message',
            '/reset — begin a new conversation',
            '/help — show this list',
        ];

        foreach ($bot->custom_commands ?? [] as $command) {
            $lines[] = "/{$command['command']} — {$command['description']}";
        }

        $lines[] = '';
        $lines[] = 'Or just send a message — you can ask me anything.';

        return implode("\n", $lines);
    }

    /** A custom command's canned reply, or null to fall through if it's undefined. */
    private function custom(AgentTelegramBot $bot, string $command): ?string
    {
        foreach ($bot->custom_commands ?? [] as $entry) {
            if (($entry['command'] ?? null) === $command) {
                return $entry['reply'] ?? null;
            }
        }

        return null;
    }
}
