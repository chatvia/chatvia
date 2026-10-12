<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Data;

use App\Identity\Chatter\Actions\ResetChatterPasswordWithTokenAction;
use App\Identity\Chatter\Models\Chatter;
use Spatie\LaravelData\Data;

/**
 * Input for {@see ResetChatterPasswordWithTokenAction}: the chatter named by
 * the reset link, the link's token and the new password.
 */
class ResetChatterPasswordWithTokenData extends Data
{
    public function __construct(
        public Chatter $chatter,
        #[\SensitiveParameter]
        public string $token,
        #[\SensitiveParameter]
        public string $password,
    ) {}
}
