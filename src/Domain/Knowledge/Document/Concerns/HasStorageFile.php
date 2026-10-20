<?php

namespace Domain\Knowledge\Document\Concerns;

use Closure;
use Domain\Knowledge\Document\Conversion\OfficePdf;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Illuminate\Support\Facades\File as LocalFiles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Read helpers for a model whose file lives on the default Storage disk under
 * a path column (`filepath`, and `parser_filepath` on sources that keep a
 * parsed copy): the knowledge sources and chat attachments. Local copies are
 * temporary files: prefer readContents() or withLocalFile(), which leave
 * nothing behind.
 *
 * @property int $id
 * @property string|null $filepath
 * @property string|null $parser_filepath
 */
trait HasStorageFile
{
    /**
     * Copy the stored file in $field to one local temp file and return it. The
     * caller owns that file and must delete it; withLocalFile() does this for you.
     */
    public function readFile(string $field = 'filepath'): File
    {
        $path = $this->storedPath($field);
        $data = Storage::get($path);

        if ($data === null) {
            throw new RuntimeException("File not found in storage: {$path}");
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $tempPath = sys_get_temp_dir().'/storage_file_'.Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');

        LocalFiles::put($tempPath, $data);

        return new File($tempPath);
    }

    /**
     * The raw contents of the stored file in $field, without a local copy.
     */
    public function readContents(string $field = 'filepath'): string
    {
        $path = $this->storedPath($field);
        $data = Storage::get($path);

        if ($data === null) {
            throw new RuntimeException("File not found in storage: {$path}");
        }

        return $data;
    }

    /**
     * Run $callback with a local copy of the stored file in $field, and delete
     * the copy afterwards — also when the callback throws.
     *
     * @template TReturn
     *
     * @param  Closure(File): TReturn  $callback
     * @return TReturn
     */
    public function withLocalFile(Closure $callback, string $field = 'filepath'): mixed
    {
        $file = $this->readFile($field);

        try {
            return $callback($file);
        } finally {
            LocalFiles::delete($file->getPathname());
        }
    }

    /**
     * Run $callback with the Storage path of a PDF version of this file.
     *
     * A stored PDF is passed as-is and is never deleted. Anything else becomes
     * a temporary PDF of its text ({@see OfficePdf}) under the `tmp/` folder of
     * the file's own storage root (e.g. `workspaces/{external_id}/tmp/`), so a
     * workspace delete also wipes it;
     * that copy and every local conversion file are deleted after the callback.
     * `parser_filepath` is used when present, because it holds the readable
     * text: a media transcript, or the parsed pages (JSON) of a document or
     * image.
     *
     * @template TReturn
     *
     * @param  OfficePdf  $pdf  Makes the PDF of a non-PDF file.
     * @param  Closure(string): TReturn  $callback
     * @return TReturn
     */
    public function withPdf(OfficePdf $pdf, Closure $callback): mixed
    {
        if (strtolower(pathinfo((string) $this->filepath, PATHINFO_EXTENSION)) === 'pdf') {
            return $callback($this->storedPath('filepath'));
        }

        // `parser_filepath` only exists on SourceFile. SourceText and
        // SourceWebsite share this trait without that column, so read the raw
        // attribute — under `Model::shouldBeStrict()` `$this->parser_filepath`
        // would throw MissingAttributeException instead of falling back.
        $field = ($this->getAttributes()['parser_filepath'] ?? null) ? 'parser_filepath' : 'filepath';
        $extension = strtolower(pathinfo((string) ($this->getAttributes()[$field] ?? ''), PATHINFO_EXTENSION));

        $localPdf = $extension === 'json'
            ? $pdf->fromPages(PageData::fromJson($this->readContents($field))->pluck('content')->all())
            : $this->withLocalFile(fn (File $file): string => $pdf->toPdf($file->getPathname(), $extension), $field);

        // The temporary PDF goes under the same entity-first root as the file
        // itself (`workspaces/{external_id}`), so deleting the workspace also
        // removes a copy a crash left behind. Every model sharing this trait
        // stores its file under that root, including chat attachments, which
        // have no workspace column of their own.
        $root = implode('/', array_slice(explode('/', (string) ($this->getAttributes()['filepath'] ?? '')), 0, 2));
        $pdfPath = ($root !== '' ? $root : 'tmp').'/tmp/'.Str::uuid()->toString().'.pdf';

        try {
            Storage::put($pdfPath, LocalFiles::get($localPdf));
        } finally {
            LocalFiles::delete($localPdf);
        }

        try {
            return $callback($pdfPath);
        } finally {
            Storage::delete($pdfPath);
        }
    }

    private function storedPath(string $field): string
    {
        $path = $this->getAttribute($field);

        if (blank($path)) {
            throw new RuntimeException(sprintf('No stored file in "%s" for %s #%s.', $field, class_basename($this), $this->getKey()));
        }

        return $path;
    }
}
