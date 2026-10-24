<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Document;

use Illuminate\Support\Str;
use ZipArchive;

/**
 * Builds a small EPUB book for tests of the ebook readers: an OPF package
 * file under OEBPS/ and one XHTML file per chapter. The chapters are read in
 * the order they were added unless {@see spine()} names another; the manifest
 * gives them the ids c0, c1, ... in the order they were added. {@see save()}
 * writes it to the temp folder; the caller deletes it.
 */
final class EpubFile
{
    /** @var array<string, string> path under OEBPS/ → XHTML body */
    private array $chapters = [];

    /** @var list<string>|null manifest ids in reading order */
    private ?array $spine = null;

    /** The chapter that is the book's contents page, if any. */
    private string $nav = '';

    public function chapter(string $href, string $body): self
    {
        $this->chapters[$href] = $body;

        return $this;
    }

    public function spine(string ...$ids): self
    {
        $this->spine = array_values($ids);

        return $this;
    }

    /** Marks the chapter at $href as the contents page (`properties="nav"`). */
    public function nav(string $href): self
    {
        $this->nav = $href;

        return $this;
    }

    public function save(): string
    {
        $manifest = '';

        foreach (array_keys($this->chapters) as $index => $href) {
            $manifest .= '<item id="c'.$index.'" href="'.Str::replace(' ', '%20', $href).'" media-type="application/xhtml+xml"'.($href === $this->nav ? ' properties="nav"' : '').'/>';
        }

        $spine = $this->spine ?? array_map(static fn (int $index): string => 'c'.$index, array_keys(array_keys($this->chapters)));

        $path = create_temp_file('book.epub', '');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf"/></rootfiles></container>');
        $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf"><manifest>'.$manifest.'</manifest><spine>'
            .implode('', array_map(static fn (string $id): string => '<itemref idref="'.$id.'"/>', $spine)).'</spine></package>');

        foreach ($this->chapters as $href => $body) {
            $zip->addFromString('OEBPS/'.$href, '<?xml version="1.0" encoding="utf-8"?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'
                .'<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><head><title>t</title><style>p { margin: 0 }</style></head><body>'.$body.'</body></html>');
        }

        $zip->close();

        return $path;
    }
}
