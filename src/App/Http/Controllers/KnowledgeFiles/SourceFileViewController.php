<?php

namespace App\Http\Controllers\KnowledgeFiles;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class SourceFileViewController
{
    public function __invoke(Request $request, string $type, string $externalId)
    {
        $allowedType = ['file', 'text', 'website'];

        if (! in_array($type, $allowedType, true)) {
            abort(404);
        }

        if (! Str::isUuid($externalId)) {
            abort(404);
        }

        $model = match ($type) {
            'file' => SourceFile::class,
            'text' => SourceText::class,
            'website' => SourceWebsite::class,
        };

        $record = $model::query()
            ->where('external_id', $externalId)
            ->first();

        if (! $record?->filepath) {
            abort(404, 'Invalid source file.');
        }

        // Tenant isolation lives in the source policies (view → workspace
        // ownership). 404 — not 403 — so the endpoint does not confirm the
        // existence of a file the caller may not see.
        abort_unless($request->user()->can('view', $record), 404);

        $filepath = $record->filepath;

        if (! Storage::exists($filepath)) {
            abort(404, 'File not found.');
        }

        $stream = Storage::readStream($filepath);

        if ($stream === false) {
            abort(404, 'File not found.');
        }

        $filename = basename($filepath);
        $mimeType = Storage::mimeType($filepath) ?: 'application/octet-stream';
        $size = Storage::size($filepath);

        $dispositionType = $request->boolean('download') ? 'attachment' : 'inline';

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition($dispositionType, $filename),
        ];

        if (is_int($size)) {
            $headers['Content-Length'] = (string) $size;
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
