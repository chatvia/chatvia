<?php

namespace Domain\Conversation\Topic\Exceptions;

use Support\Exceptions\DomainValidationException;

/**
 * Thrown when an actor tries to modify or delete a system-owned Topic
 * (user_id NULL). Distinct from a generic validation failure: the right
 * HTTP status is 403, not 422.
 */
class SystemTopicProtectedException extends DomainValidationException {}
