<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Magic;

use App\Http\Auth\MagicLinkSignIn;

/**
 * The previous name of {@see MagicLinkSignIn}. Magic links store their action
 * serialized, so links emailed before the rename still name this class; it is
 * kept until those links have expired (they live 15 minutes), then can go.
 *
 * @deprecated use {@see MagicLinkSignIn}
 */
class TwoFactorAwareLoginAction extends MagicLinkSignIn {}
