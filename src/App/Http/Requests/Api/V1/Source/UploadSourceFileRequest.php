<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Source;

use App\Http\Requests\Concerns\ReadsUploadedSourceFile;
use App\Http\Rules\UnlockedPdf;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a knowledge-file upload through the public API. Access to the
 * agent is checked by the route middleware.
 */
class UploadSourceFileRequest extends FormRequest
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
            'file' => ['required', 'file', new UnlockedPdf],
        ];
    }

    public function toData(): UploadedSourceFileData
    {
        return $this->uploadedSourceFile();
    }
}
