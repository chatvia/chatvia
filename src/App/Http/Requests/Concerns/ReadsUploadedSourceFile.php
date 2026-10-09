<?php

namespace App\Http\Requests\Concerns;

use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Illuminate\Http\UploadedFile;

/**
 * Turns the request's `file` upload into the Domain's description of an
 * uploaded knowledge file, so no HTTP upload object reaches the Domain layer.
 * Shared by the dashboard and API knowledge-file upload requests.
 */
trait ReadsUploadedSourceFile
{
    protected function uploadedSourceFile(): UploadedSourceFileData
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return new UploadedSourceFileData(
            path: $file->getPathname(),
            originalName: $file->getClientOriginalName(),
            mimeType: (string) $file->getMimeType(),
            clientMimeType: $file->getClientMimeType(),
            sizeInBytes: (int) $file->getSize(),
        );
    }
}
