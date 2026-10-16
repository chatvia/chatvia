<?php

namespace Domain\Conversation\Services\Export;

use League\CommonMark\ConverterInterface;

/**
 * Renders a conversation's messages for download: joined Markdown, plain text
 * (tags stripped from the rendered HTML), or per-message HTML for the PDF
 * layout.
 */
class ChatTranscriptRenderer
{
    /**
     * Separator between messages in the text and Markdown files: a line of
     * its own with a blank line on each side, so Markdown reads it as a rule
     * and not as the underline of a heading.
     */
    private const string MESSAGE_SEPARATOR = "\n\n---\n\n";

    public function __construct(
        private readonly ConverterInterface $markdown,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function toMarkdown(array $messages): string
    {
        return $this->joined($messages, static fn (array $message): string => (string) $message['content']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function toText(array $messages): string
    {
        return $this->joined($messages, fn (array $message): string => strip_tags($this->html($message)));
    }

    /**
     * The messages as the PDF view lays them out.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return list<array{role: mixed, html: string}>
     */
    public function toPdfMessages(array $messages): array
    {
        return array_values(array_map(fn (array $message): array => [
            'role' => $message['role'],
            'html' => $this->html($message),
        ], $messages));
    }

    /**
     * A message's Markdown as HTML (raw HTML in it is stripped by the converter
     * settings, config/markdown.php).
     *
     * @param  array<string, mixed>  $message
     */
    private function html(array $message): string
    {
        return $this->markdown->convert((string) $message['content'])->getContent();
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  callable(array<string, mixed>): string  $render
     */
    private function joined(array $messages, callable $render): string
    {
        return collect($messages)
            ->map(static fn (array $message): string => trim($render($message)))
            ->implode(self::MESSAGE_SEPARATOR)."\n";
    }
}
