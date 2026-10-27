<?php

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Tool\Data\ToolDispatchResult;

/**
 * A `tools/call` result envelope (`{content, isError?, structuredContent?}`)
 * split into what the model reads and what the chat UI renders. McpClient
 * builds it from the server's reply, so the envelope is parsed in one place:
 *
 *   - `text`      → the `type: 'text'` parts of `content`, joined; a JSON dump
 *                   of the whole envelope when there are none.
 *   - artifacts   → well-known `structuredContent` shapes promoted to UI
 *                   blocks (an ECharts option, an inline form, a booking calendar). Unknown keys
 *                   are ignored, so servers can ship side data safely.
 *
 * Artifacts are taken only from Chatvia's own servers
 * ({@see fromFirstPartyEnvelope()}). The chat renders them in the visitor's
 * and the dashboard's page, so a block from any other server (a workspace's
 * custom MCP server, a third-party connector) could carry markup or links
 * the renderer would act on; its structured content is ignored.
 */
final readonly class McpToolResultData
{
    /**
     * @param  array<string, mixed>|null  $structuredContent
     */
    public function __construct(
        public string $text,
        public bool $isError,
        public ?array $structuredContent,
        public bool $fromFirstParty = false,
    ) {}

    /**
     * The result of a server Chatvia does not run: text only, never artifacts.
     *
     * @param  array<string, mixed>  $envelope
     */
    public static function fromEnvelope(array $envelope): self
    {
        $texts = [];
        foreach ((array) ($envelope['content'] ?? []) as $part) {
            if (is_array($part) && ($part['type'] ?? null) === 'text' && isset($part['text'])) {
                $texts[] = (string) $part['text'];
            }
        }

        return new self(
            text: $texts === [] ? (string) json_encode($envelope) : implode("\n", $texts),
            isError: (bool) ($envelope['isError'] ?? false),
            structuredContent: is_array($envelope['structuredContent'] ?? null) ? $envelope['structuredContent'] : null,
        );
    }

    /**
     * The result of one of Chatvia's own servers (charts, forms, analytics),
     * whose artifacts the chat may render.
     *
     * @param  array<string, mixed>  $envelope
     */
    public static function fromFirstPartyEnvelope(array $envelope): self
    {
        $result = self::fromEnvelope($envelope);

        return new self($result->text, $result->isError, $result->structuredContent, fromFirstParty: true);
    }

    /**
     * As the tool layer's result: the text plus the renderable blocks, or a
     * recoverable error carrying the tool's own error text.
     */
    public function toDispatchResult(): ToolDispatchResult
    {
        return $this->isError
            ? ToolDispatchResult::error($this->text)
            : new ToolDispatchResult($this->text, $this->artifacts());
    }

    /**
     * The renderable blocks, each `['kind' => …, 'data' => …]` as the chat
     * renderer expects:
     *
     *   - `echarts_option` → an interactive chart (Charts tools).
     *   - `form`           → an inline FormKit form (RenderFormTool).
     *   - `calendar`       → a booking calendar (RenderCalendarTool).
     *   - `image`, `audio`, `video` → a generated file, pending until its job
     *                        is done (GenerateImageTool, GenerateAudioTool, GenerateVideoTool).
     *
     * @return list<array{kind: string, data: array<string, mixed>}>
     */
    public function artifacts(): array
    {
        if (! $this->fromFirstParty) {
            return [];
        }

        $artifacts = [];

        foreach (['echarts_option', 'form', 'calendar', 'image', 'audio', 'video'] as $kind) {
            if (is_array($this->structuredContent[$kind] ?? null)) {
                $artifacts[] = ['kind' => $kind, 'data' => $this->structuredContent[$kind]];
            }
        }

        return $artifacts;
    }
}
