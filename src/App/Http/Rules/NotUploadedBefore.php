<?php

declare(strict_types=1);

namespace App\Http\Rules;

use App\Identity\Workspace\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A knowledge file the workspace already uploaded is refused, by its content
 * (the MD5 checksum stored on each source file), so the Files page does not
 * train the same file twice. Other values pass.
 */
final readonly class NotUploadedBefore implements ValidationRule
{
    public function __construct(
        private Workspace $workspace,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        if ($this->workspace->sourceFiles()->where('checksum', md5_file($value->getPathname()))->exists()) {
            $fail(__('You have already uploaded this file.'));
        }
    }
}
