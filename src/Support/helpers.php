<?php

/*
 * The application's only global helpers file (autoloaded through
 * composer.json). Each helper is a small, framework-level utility with no
 * business rules: domain logic, authorization and service lookups belong in
 * classes that are injected where they are needed, never in a global function.
 */

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Support\MimeType;
use Support\Prompt\PromptTemplate;

if (! function_exists('create_temp_dir')) {
    /**
     * The system temp directory, or a subdirectory of it that is created when
     * it does not exist yet.
     *
     * @param  string  $append  Path to add to the temp directory, starting with `/`.
     * @return string The directory path.
     *
     * @throws RuntimeException when the subdirectory cannot be created.
     */
    function create_temp_dir(string $append = ''): string
    {
        $path = sys_get_temp_dir().$append;

        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        return $path;
    }
}

if (! function_exists('cache_dir')) {
    /**
     * The directory of the file cache store (`cache.stores.file.path`).
     */
    function cache_dir(): string
    {
        return (string) config('cache.stores.file.path');
    }
}

if (! function_exists('create_temp_file')) {
    /**
     * Write $content to a new, uniquely named file in the temp directory. The
     * file gets the extension of $filename, so tools that sniff by extension
     * read it correctly. The caller owns the file and must delete it.
     *
     * @param  string  $filename  Any name or path; only its extension is used.
     * @return string The path of the new file.
     */
    function create_temp_file(string $filename, string $content): string
    {
        $tempFile = create_temp_dir().'/'.uniqid('', true).'.'.File::extension($filename);

        file_put_contents($tempFile, $content);

        return $tempFile;
    }
}

if (! function_exists('file_extension')) {
    /**
     * Lowercased extension from the original filename (UploadedFile) or path
     * (string). Empty when there is none. Extension — not MIME — is the source
     * of truth for document formats, because exotic text types share generic
     * MIME types (.tex/.bib → text/plain).
     */
    function file_extension(UploadedFile|string $file): string
    {
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file;

        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }
}

if (! function_exists('is_pdf_file')) {
    /**
     * Whether the file's content is a PDF, by its detected MIME type.
     *
     * @param  UploadedFile|string  $file  An upload or a local path.
     */
    function is_pdf_file(UploadedFile|string $file): bool
    {
        $path = $file instanceof UploadedFile ? $file->path() : $file;

        return MimeType::isPdf((string) File::mimeType($path));
    }
}

if (! function_exists('is_audio_file')) {
    /**
     * Whether the file's content is audio, by its detected MIME type.
     *
     * @param  UploadedFile|string  $file  An upload or a local path.
     */
    function is_audio_file(UploadedFile|string $file): bool
    {
        $path = $file instanceof UploadedFile ? $file->path() : $file;

        return MimeType::isAudio((string) File::mimeType($path));
    }
}

if (! function_exists('is_video_file')) {
    /**
     * Whether the file's content is video, by its detected MIME type.
     *
     * @param  UploadedFile|string  $file  An upload or a local path.
     */
    function is_video_file(UploadedFile|string $file): bool
    {
        $path = $file instanceof UploadedFile ? $file->path() : $file;

        return MimeType::isVideo((string) File::mimeType($path));
    }
}

if (! function_exists('template')) {
    /**
     * Replace each `{key}` in $template with its value, e.g.
     * `template('agent:{id}', ['id' => 7])` → `agent:7`.
     *
     * @param  array<string, string|int|float>  $placeholders
     */
    function template(string $template, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        return $template;
    }
}

if (! function_exists('prompt')) {
    /**
     * Render a prompt Blade view (markdown + Blade) to a string.
     *
     * Accepts slash- or dot-style names: prompt('business/profile')
     * resolves resources/prompts/business/profile.blade.php via the
     * `prompts::` view namespace. Pass variables as the second argument; use
     * {!! $var !!} in the prompt file so text isn't HTML-escaped.
     *
     * A prompt an admin edited (Settings → Prompts, `chatvia.prompts`) is
     * rendered instead of its file, but only when it keeps to the safe part of
     * Blade ({@see PromptTemplate}); an edit that is unsafe or fails to render
     * is reported and the file is used.
     *
     * @param  array<string, mixed>  $data
     */
    function prompt(string $name, array $data = []): string
    {
        $edited = ((array) config('chatvia.prompts'))[str_replace('.', '/', $name)] ?? null;

        if (is_string($edited) && trim($edited) !== '') {
            try {
                if (! PromptTemplate::isSafe($edited)) {
                    throw new RuntimeException("The edited prompt \"{$name}\" uses Blade that is not allowed; its file is used.");
                }

                return Blade::render($edited, $data, deleteCachedView: false);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return view('prompts::'.str_replace('/', '.', $name), $data)->render();
    }
}
