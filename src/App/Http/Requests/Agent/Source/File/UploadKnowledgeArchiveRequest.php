<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\File;

use App\Http\Requests\Concerns\ReadsUploadedSourceFile;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a zip archive of knowledge files uploaded from the agent's Files
 * page. The files inside are each checked by the upload action; access is
 * checked by the route middleware.
 */
class UploadKnowledgeArchiveRequest extends FormRequest
{
    use ReadsUploadedSourceFile;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:zip', 'max:'.SourceFileTypes::MAX_UPLOAD_KB],
        ];
    }

    public function toData(): UploadedSourceFileData
    {
        return $this->uploadedSourceFile();
    }
}
