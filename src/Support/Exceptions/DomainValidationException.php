<?php

declare(strict_types=1);

namespace Support\Exceptions;

use RuntimeException;

/**
 * Field-keyed rejection from a Domain Action. Bubbles up to the global
 * exception handler in bootstrap/app.php, which maps it to a 422
 * ValidationException — JSON gets {message, errors:{field:[...]}}, web
 * redirects back with the field error.
 *
 * Controllers can also catch it directly when they need a different
 * response shape (a flash, a redirect, a 403 — see the Topic domain's
 * SystemTopicProtectedException).
 *
 * It lives in Support, not in one domain, because every domain throws it: a
 * small shared-kernel type that depends on nothing but PHP itself.
 */
class DomainValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
