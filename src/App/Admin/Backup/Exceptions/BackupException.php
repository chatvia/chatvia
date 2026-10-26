<?php

declare(strict_types=1);

namespace App\Admin\Backup\Exceptions;

use RuntimeException;

/**
 * A step of a backup failed: a program could not run or gave no usable
 * output. The message is saved on the backup for the admin to read, so it
 * never holds a password.
 */
class BackupException extends RuntimeException {}
