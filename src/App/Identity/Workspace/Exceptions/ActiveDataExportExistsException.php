<?php

namespace App\Identity\Workspace\Exceptions;

use RuntimeException;

class ActiveDataExportExistsException extends RuntimeException
{
    public static function forUser(int $userId): self
    {
        return new self("User {$userId} already has an active data export.");
    }
}
