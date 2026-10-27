<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads the text of an old PowerPoint presentation (.ppt, PowerPoint 97 to
 * 2003) slide by slide, with no catppt on the server. Each slide gives the
 * paragraphs of its titles, text boxes and table cells, one per line, as the
 * reader of the newer .pptx files does.
 *
 * The file is a stream of records (MS-PPT). The last save names, through
 * its persist directory, the document and the slides in their current
 * version, so text an earlier save replaced is not read. A slide's
 * placeholders (title, body) keep their text in the document's slide list;
 * other shapes keep it in the slide's drawing.
 *
 * Speaker notes and master slides are left out; password-protected files
 * are refused.
 */
final class PptReader
{
    private const int CONTAINER = 0x0F;

    private const int DOCUMENT = 0x03E8;

    private const int SLIDE = 0x03EE;

    private const int SLIDE_PERSIST = 0x03F3;

    private const int SLIDE_LIST = 0x0FF0;

    private const int OUTLINE_TEXT_REFERENCE = 0x0F9E;

    private const int TEXT_HEADER = 0x0F9F;

    private const int TEXT_CHARS = 0x0FA0;

    private const int TEXT_BYTES = 0x0FA8;

    private const int USER_EDIT = 0x0FF5;

    private const int PERSIST_DIRECTORY = 0x1772;

    private const int CLIENT_TEXTBOX = 0xF00D;

    private const int ENCRYPTED_TOKEN = 0xF3D1C4DF;

    /** Deepest nesting of records followed; real files stay far below it. */
    private const int MAX_DEPTH = 32;

    private string $stream = '';

    /**
     * @return list<string>
     */
    public function slides(string $path): array
    {
        $file = CompoundFile::open($path);

        try {
            $this->stream = $file->read('PowerPoint Document') ?? throw new RuntimeException('The file is not a PowerPoint presentation.');
            $currentUser = $file->read('Current User');
        } finally {
            $file->close();
        }

        try {
            $persist = $this->persistDirectory($currentUser ?? '');

            return $persist === null ? $this->slidesInStreamOrder() : $this->slidesInShowOrder(...$persist);
        } finally {
            $this->stream = '';
        }
    }

    /** All slides as Markdown, one block per slide. */
    public function toMarkdown(string $path): string
    {
        return Arr::join(array_filter($this->slides($path), static fn (string $slide): bool => $slide !== ''), "\n\n");
    }

    /**
     * The persist directory of the last save (object id → offset of the
     * object in the stream, newer saves first) and the document's id, or
     * null when the file does not have one that holds together.
     *
     * @return array{0: array<int, int>, 1: int}|null
     */
    private function persistDirectory(string $currentUser): ?array
    {
        if ($this->uint32($currentUser, 12) === self::ENCRYPTED_TOKEN) {
            throw new RuntimeException('The PowerPoint presentation is password protected.');
        }

        $offset = $this->uint32($currentUser, 16);
        $directory = [];
        $document = null;
        $seen = [];

        while ($offset > 0 && ! isset($seen[$offset])) {
            $seen[$offset] = true;
            $edit = $this->header($offset);

            if ($edit === null || $edit['type'] !== self::USER_EDIT) {
                break;
            }

            $document ??= $this->uint32($this->stream, $offset + 24);
            $list = $this->header($this->uint32($this->stream, $offset + 20));

            if ($list !== null && $list['type'] === self::PERSIST_DIRECTORY) {
                $position = $list['start'];
                $end = $list['start'] + $list['length'];

                while ($position + 4 <= $end) {
                    $entry = $this->uint32($this->stream, $position);
                    $first = $entry & 0xFFFFF;
                    $count = $entry >> 20;
                    $position += 4;

                    for ($index = 0; $index < $count && $position + 4 <= $end; $index++, $position += 4) {
                        $directory[$first + $index] ??= $this->uint32($this->stream, $position);
                    }
                }
            }

            $offset = $this->uint32($this->stream, $offset + 16);
        }

        return $document === null || ! isset($directory[$document]) ? null : [$directory, $document];
    }

    /**
     * The slides in the order of the slide list, each read from its current
     * version.
     *
     * @param  array<int, int>  $directory
     * @return list<string>
     */
    private function slidesInShowOrder(array $directory, int $document): array
    {
        $container = $this->header($directory[$document]);

        if ($container === null || $container['type'] !== self::DOCUMENT) {
            return $this->slidesInStreamOrder();
        }

        $slides = [];

        foreach ($this->slideList($container) as $id => $texts) {
            $slide = isset($directory[$id]) ? $this->header($directory[$id]) : null;

            $slides[] = $this->slideText($slide !== null && $slide['type'] === self::SLIDE ? $slide : null, $texts);
        }

        return $slides;
    }

    /**
     * Without a persist directory: every slide record in the stream, in the
     * order they are stored.
     *
     * @return list<string>
     */
    private function slidesInStreamOrder(): array
    {
        $slides = [];

        foreach ($this->records(0, strlen($this->stream)) as $record) {
            if ($record['type'] === self::SLIDE) {
                $slides[] = $this->slideText($record, []);
            }
        }

        return $slides;
    }

    /**
     * The placeholder text of each slide in the document's slide list,
     * keyed by the slide's object id.
     *
     * @param  array{type: int, instance: int, version: int, start: int, length: int}  $document
     * @return array<int, list<string>>
     */
    private function slideList(array $document): array
    {
        $slides = [];

        foreach ($this->records($document['start'], $document['start'] + $document['length']) as $list) {
            if ($list['type'] !== self::SLIDE_LIST || $list['instance'] !== 0) {
                continue;
            }

            $current = null;

            foreach ($this->records($list['start'], $list['start'] + $list['length']) as $record) {
                if ($record['type'] === self::SLIDE_PERSIST) {
                    $current = $this->uint32($this->stream, $record['start']);
                    $slides[$current] = [];
                } elseif ($current !== null && $record['type'] === self::TEXT_HEADER) {
                    $slides[$current][] = '';
                } elseif ($current !== null && collect([self::TEXT_CHARS, self::TEXT_BYTES])->containsStrict($record['type']) && $slides[$current] !== []) {
                    $slides[$current][array_key_last($slides[$current])] = $this->text($record);
                }
            }
        }

        return $slides;
    }

    /**
     * A slide's text in the order of its shapes: a placeholder's text from
     * the slide list, any other shape's from the drawing. Slide-list text no
     * shape points to comes last.
     *
     * @param  array{type: int, instance: int, version: int, start: int, length: int}|null  $slide
     * @param  list<string>  $placeholders
     */
    private function slideText(?array $slide, array $placeholders): string
    {
        $texts = [];
        $used = [];

        foreach ($slide === null ? [] : $this->textBoxes($slide, 0) as $box) {
            $parts = [];

            foreach ($box as $record) {
                if ($record['type'] === self::OUTLINE_TEXT_REFERENCE) {
                    $index = $this->uint32($this->stream, $record['start']);
                    $used[$index] = true;
                    $parts[] = $placeholders[$index] ?? '';
                } else {
                    $parts[] = $this->text($record);
                }
            }

            $texts[] = Arr::join($parts, "\n");
        }

        foreach ($placeholders as $index => $text) {
            if (! isset($used[$index]) && ! collect($texts)->containsStrict($text)) {
                $texts[] = $text;
            }
        }

        $lines = [];

        foreach (collect($texts)->flatMap(fn (string $text): Collection => Str::of($text)->explode("\n")) as $line) {
            $line = trim((string) preg_replace('/[ \t]+/u', ' ', $line));

            // A lone "*" stands for a field such as the slide number.
            if ($line !== '' && $line !== '*') {
                $lines[] = $line;
            }
        }

        return Arr::join($lines, "\n");
    }

    /**
     * The text records of each text box in a slide's drawing, in shape
     * order.
     *
     * @param  array{type: int, instance: int, version: int, start: int, length: int}  $container
     * @return list<list<array{type: int, instance: int, version: int, start: int, length: int}>>
     */
    private function textBoxes(array $container, int $depth): array
    {
        $boxes = [];

        foreach ($this->records($container['start'], $container['start'] + $container['length']) as $record) {
            if ($record['type'] === self::CLIENT_TEXTBOX) {
                $boxes[] = array_values(array_filter(
                    $this->records($record['start'], $record['start'] + $record['length']),
                    static fn (array $inner): bool => collect([self::TEXT_CHARS, self::TEXT_BYTES, self::OUTLINE_TEXT_REFERENCE])->containsStrict($inner['type']),
                ));
            } elseif ($record['version'] === self::CONTAINER && $depth < self::MAX_DEPTH) {
                array_push($boxes, ...$this->textBoxes($record, $depth + 1));
            }
        }

        return $boxes;
    }

    /**
     * The text of a text atom: UTF-16, or one byte per character (the low
     * byte of UTF-16, so Latin-1). Paragraphs end with a carriage return.
     *
     * @param  array{type: int, instance: int, version: int, start: int, length: int}  $record
     */
    private function text(array $record): string
    {
        $bytes = substr($this->stream, $record['start'], $record['length']);

        $text = $record['type'] === self::TEXT_CHARS
            ? mb_convert_encoding(substr($bytes, 0, strlen($bytes) - strlen($bytes) % 2), 'UTF-8', 'UTF-16LE')
            : mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-1');

        $text = str_replace(["\r", "\x0B", "\u{A0}"], ["\n", "\n", ' '], $text);

        return (string) preg_replace('/[\x00-\x08\x0C\x0E-\x1F]/', '', $text);
    }

    /**
     * The records stored one after another in a range of the stream.
     *
     * @return list<array{type: int, instance: int, version: int, start: int, length: int}>
     */
    private function records(int $start, int $end): array
    {
        $records = [];
        $end = min($end, strlen($this->stream));

        while ($start + 8 <= $end) {
            $record = $this->header($start);

            if ($record === null) {
                break;
            }

            $record['length'] = min($record['length'], $end - $record['start']);
            $records[] = $record;
            $start = $record['start'] + $record['length'];
        }

        return $records;
    }

    /**
     * The record at an offset: its type, instance, version, and where its
     * content starts and how long it is.
     *
     * @return array{type: int, instance: int, version: int, start: int, length: int}|null
     */
    private function header(int $offset): ?array
    {
        if ($offset < 0 || $offset + 8 > strlen($this->stream)) {
            return null;
        }

        $options = $this->uint16($this->stream, $offset);

        return [
            'type' => $this->uint16($this->stream, $offset + 2),
            'instance' => $options >> 4,
            'version' => $options & 0x0F,
            'start' => $offset + 8,
            'length' => min($this->uint32($this->stream, $offset + 4), strlen($this->stream) - $offset - 8),
        ];
    }

    private function uint16(string $bytes, int $offset): int
    {
        return $offset >= 0 && strlen($bytes) >= $offset + 2 ? (int) unpack('v', $bytes, $offset)[1] : 0;
    }

    private function uint32(string $bytes, int $offset): int
    {
        return $offset >= 0 && strlen($bytes) >= $offset + 4 ? (int) unpack('V', $bytes, $offset)[1] : 0;
    }
}
