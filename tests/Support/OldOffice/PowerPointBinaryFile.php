<?php

declare(strict_types=1);

namespace Tests\Support\OldOffice;

/**
 * Builds small PowerPoint presentations in the binary format (.ppt), for
 * tests of the built-in reader. Each slide has placeholder text, kept in the
 * document's slide list as PowerPoint keeps it, and text boxes, kept in the
 * slide's drawing. The slides are stored in reverse order, a master slide
 * holds text of its own, and an earlier save can leave an older version of a
 * slide in the file: the reader must follow the slide list and the last
 * save, not the order of the records.
 */
final class PowerPointBinaryFile
{
    private const int ENCRYPTED_TOKEN = 0xF3D1C4DF;

    private const int PLAIN_TOKEN = 0xE391C05F;

    /** @var list<array{placeholders: list<string>, boxes: list<string>}> */
    private array $slides = [];

    /** @var list<string>|null the first slide's boxes in an earlier save */
    private ?array $earlierFirstSlide = null;

    private bool $encrypted = false;

    /**
     * @param  list<string>  $placeholders  the title and body text, paragraphs split by "\r"
     * @param  list<string>  $boxes  the text boxes
     */
    public function slide(array $placeholders, array $boxes = []): self
    {
        $this->slides[] = ['placeholders' => $placeholders, 'boxes' => $boxes];

        return $this;
    }

    /**
     * @param  list<string>  $boxes
     */
    public function earlierSaveOfFirstSlide(array $boxes): self
    {
        $this->earlierFirstSlide = $boxes;

        return $this;
    }

    public function encrypted(): self
    {
        $this->encrypted = true;

        return $this;
    }

    /** Writes the presentation to a temporary file and returns its path. */
    public function save(): string
    {
        $stream = '';
        $lastEdit = 0;

        if ($this->earlierFirstSlide !== null) {
            $old = strlen($stream);
            $stream .= $this->slideContainer([], $this->earlierFirstSlide);
            $directory = strlen($stream);
            $stream .= self::atom(0x1772, pack('V', 2 | (1 << 20)).pack('V', $old));
            $lastEdit = strlen($stream);
            $stream .= $this->userEdit(0, $directory);
        }

        $offsets = [];

        // The slides in reverse order, so stream order is not show order.
        foreach (array_reverse(array_keys($this->slides), true) as $index) {
            $offsets[$index] = strlen($stream);
            $stream .= $this->slideContainer($this->slides[$index]['placeholders'], $this->slides[$index]['boxes']);
        }

        $document = strlen($stream);
        $stream .= $this->documentContainer();

        $entries = [$document];

        foreach (array_keys($this->slides) as $index) {
            $entries[] = $offsets[$index];
        }

        $directory = strlen($stream);
        $stream .= self::atom(0x1772, pack('V', 1 | (count($entries) << 20)).pack('V*', ...$entries));
        $currentEdit = strlen($stream);
        $stream .= $this->userEdit($lastEdit, $directory);

        $currentUser = self::atom(0x0FF6, pack('VVVvvCCv', 0x14, $this->encrypted ? self::ENCRYPTED_TOKEN : self::PLAIN_TOKEN, $currentEdit, 4, 0x03F4, 3, 0, 0).'test');

        $path = (string) tempnam(sys_get_temp_dir(), 'ppt');
        file_put_contents($path, CompoundFileWriter::write([
            'Current User' => $currentUser,
            'PowerPoint Document' => str_pad($stream, 4096, "\0"),
        ]));

        return $path;
    }

    private function documentContainer(): string
    {
        $list = '';

        foreach ($this->slides as $index => $slide) {
            $list .= self::atom(0x03F3, pack('VVVVV', $index + 2, 0, count($slide['placeholders']), 256 + $index, 0));

            foreach ($slide['placeholders'] as $number => $text) {
                $list .= self::atom(0x0F9F, pack('V', $number === 0 ? 0 : 1)).self::text($text);
            }
        }

        $masters = self::atom(0x03F3, pack('VVVVV', 99, 0, 1, 0x80000000, 0))
            .self::atom(0x0F9F, pack('V', 0)).self::text('Click to edit Master title style');

        return self::container(0x03E8, self::atom(0x03E9, str_repeat("\0", 40))
            .self::container(0x0FF0, $masters, 1)
            .self::container(0x0FF0, $list));
    }

    /**
     * @param  list<string>  $placeholders
     * @param  list<string>  $boxes
     */
    private function slideContainer(array $placeholders, array $boxes): string
    {
        $shapes = '';

        foreach (array_keys($placeholders) as $number) {
            $shapes .= self::container(0xF004, self::atom(0xF00A, str_repeat("\0", 8)).self::container(0xF00D, self::atom(0x0F9E, pack('V', $number))));
        }

        foreach ($boxes as $text) {
            $shapes .= self::container(0xF004, self::atom(0xF00A, str_repeat("\0", 8)).self::container(0xF00D, self::atom(0x0F9F, pack('V', 4)).self::text($text)));
        }

        $drawing = self::container(0x040C, self::container(0xF002, self::container(0xF003, $shapes)));

        return self::container(0x03EE, self::atom(0x03EF, str_repeat("\0", 24)).$drawing);
    }

    private function userEdit(int $lastEdit, int $directory): string
    {
        return self::atom(0x0FF5, pack('VvCCVVVVvv', 256, 0, 0, 3, $lastEdit, $directory, 1, 100, 1, 0));
    }

    /** One byte per character for Latin-1 text, as PowerPoint stores it; UTF-16 otherwise. */
    private static function text(string $text): string
    {
        $latin1 = @iconv('UTF-8', 'ISO-8859-1', $text);

        return $latin1 !== false && $latin1 !== ''
            ? self::atom(0x0FA8, $latin1)
            : self::atom(0x0FA0, (string) mb_convert_encoding($text, 'UTF-16LE', 'UTF-8'));
    }

    private static function container(int $type, string $children, int $instance = 0): string
    {
        return pack('vvV', ($instance << 4) | 0x0F, $type, strlen($children)).$children;
    }

    private static function atom(int $type, string $body, int $instance = 0): string
    {
        return pack('vvV', $instance << 4, $type, strlen($body)).$body;
    }
}
