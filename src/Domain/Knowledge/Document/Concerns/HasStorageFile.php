<?php

namespace Domain\Knowledge\Document\Concerns;

use Closure;
use Domain\Knowledge\Document\Conversion\LibreOffice;
use Illuminate\Support\Facades\DB;
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
 * @property int $user_id
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

        file_put_contents($tempPath, $data);

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
            @unlink($file->getPathname());
        }
    }

    /**
     * Run $callback with the Storage path of a PDF version of this file.
     *
     * A stored PDF is passed as-is and is never deleted. Anything else is
     * converted with LibreOffice into a temporary PDF under the owner's
     * `users/{external_id}/tmp/` folder (so a workspace delete also wipes it);
     * that copy and every local conversion file are deleted after the callback.
     * `parser_filepath` (e.g. a media transcript) is converted when present,
     * because it holds the readable text.
     *
     * @template TReturn
     *
     * @param  LibreOffice  $office  Converts a non-PDF file.
     * @param  Closure(string): TReturn  $callback
     * @return TReturn
     */
    public function withPdf(LibreOffice $office, Closure $callback): mixed
    {
        if (strtolower(pathinfo((string) $this->filepath, PATHINFO_EXTENSION)) === 'pdf') {
            return $callback($this->storedPath('filepath'));
        }

        // `parser_filepath` only exists on SourceFile. SourceText and
        // SourceWebsite share this trait without that column, so read the raw
        // attribute — under `Model::shouldBeStrict()` `$this->parser_filepath`
        // would throw MissingAttributeException instead of falling back.
        $field = ($this->getAttributes()['parser_filepath'] ?? null) ? 'parser_filepath' : 'filepath';

        $localPdf = $this->withLocalFile(
            fn (File $file): string => $office->toPdf($file->getPathname()),
            $field,
        );

        // Resolved via a direct lookup because the models sharing this trait
        // don't all expose a user() relation.
        $ownerExternalId = DB::table('users')->where('id', $this->user_id)->value('external_id');
        $pdfPath = 'users/'.$ownerExternalId.'/tmp/'.Str::uuid()->toString().'.pdf';

        try {
            Storage::put($pdfPath, (string) file_get_contents($localPdf));
        } finally {
            @unlink($localPdf);
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

        if (! is_string($path) || $path === '') {
            throw new RuntimeException(sprintf('No stored file in "%s" for %s #%s.', $field, class_basename($this), $this->getKey()));
        }

        return $path;
    }
}
