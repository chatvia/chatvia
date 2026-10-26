<?php

declare(strict_types=1);

namespace App\Admin\Backup\Exceptions;

use InvalidArgumentException;

/**
 * The text given as the backup's public key cannot be used to encrypt: it
 * is no OpenPGP key, holds a private key or several keys, or the key is
 * expired, revoked or for signing only. The message says which.
 */
class InvalidPublicKeyException extends InvalidArgumentException {}
