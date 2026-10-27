<?php

namespace Domain\Integration\Tool\ValueObjects;

use Domain\Integration\Tool\Enums\ToolKind;
use Illuminate\Support\Str;
use Stringable;

/**
 * The name the LLM sees for a tool, and the one place that knows its format.
 *
 *   - MCP tool      → `mcp__{server_slug}__{tool}`, e.g. `mcp__github__search_issues`
 *   - Webhook tool  → the function's `internal_name`, unprefixed
 *
 * A name counts as MCP when it starts with `mcp__` and has a second `__` after
 * it. The server slug is the text before that second `__`, the tool is the
 * text after it. Either part may be empty in a malformed name, so callers
 * that need a real server and tool check {@see hasServerAndTool()}.
 *
 * The format is a wire contract: it is stored on messages, checkpoints and
 * audit rows, so composing and parsing must stay byte-identical.
 */
final readonly class ToolName implements Stringable
{
    public const string MCP_PREFIX = 'mcp__';

    public const string SEPARATOR = '__';

    /** The function-name rule LLM providers enforce (OpenAI-compatible APIs). */
    private const string LLM_NAME_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/';

    /**
     * @param  string  $value  The full name, exactly as the LLM sees it.
     * @param  string|null  $serverSlug  The MCP server slug; null for a function or an empty slug.
     * @param  string  $tool  The tool's own name: the part after the slug for MCP, the whole name for a function.
     */
    private function __construct(
        public string $value,
        public ToolKind $kind,
        public ?string $serverSlug,
        public string $tool,
    ) {}

    public static function parse(string $value): self
    {
        if (! Str::startsWith($value, self::MCP_PREFIX)) {
            return self::webhookFunction($value);
        }

        $rest = Str::chopStart($value, self::MCP_PREFIX);

        if (! Str::contains($rest, self::SEPARATOR)) {
            return self::webhookFunction($value);
        }

        $slug = Str::before($rest, self::SEPARATOR);

        return new self(
            value: $value,
            kind: ToolKind::Mcp,
            serverSlug: $slug !== '' ? $slug : null,
            tool: Str::after($rest, self::SEPARATOR),
        );
    }

    /**
     * Compose the name of a tool served by an MCP server.
     */
    public static function forMcp(string $serverSlug, string $tool): self
    {
        return self::parse(self::mcpPrefix($serverSlug).$tool);
    }

    /**
     * The prefix every tool of one MCP server shares: `mcp__{slug}__`.
     */
    public static function mcpPrefix(string $serverSlug): string
    {
        return self::MCP_PREFIX.$serverSlug.self::SEPARATOR;
    }

    public function isMcp(): bool
    {
        return $this->kind === ToolKind::Mcp;
    }

    /**
     * True for an MCP name with a non-empty server slug and tool — the only
     * kind a dispatcher or approval check can resolve to a real tool.
     */
    public function hasServerAndTool(): bool
    {
        return $this->isMcp() && filled($this->serverSlug) && filled($this->tool);
    }

    /**
     * True when this name starts with the given server's tool prefix.
     */
    public function isFromServer(string $serverSlug): bool
    {
        return Str::startsWith($this->value, self::mcpPrefix($serverSlug));
    }

    /**
     * True when LLM providers accept this as a function name.
     */
    public function isValidForLlm(): bool
    {
        return Str::isMatch(self::LLM_NAME_PATTERN, $this->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function webhookFunction(string $value): self
    {
        return new self(value: $value, kind: ToolKind::Function, serverSlug: null, tool: $value);
    }
}
