<?php

namespace App\Identity\Workspace\Exceptions;

use RuntimeException;

/**
 * The social identity is already linked to a different account.
 */
class SocialAccountAlreadyLinkedException extends RuntimeException {}
