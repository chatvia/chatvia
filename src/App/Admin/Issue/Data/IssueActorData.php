<?php

declare(strict_types=1);

namespace App\Admin\Issue\Data;

use App\Admin\Issue\Enums\IssueActorKind;
use Spatie\LaravelData\Data;

/**
 * Who was behind an issue, by id only: names and emails are read when the
 * Radar shows them, so deleting a person also removes them there.
 */
class IssueActorData extends Data
{
    public function __construct(
        public IssueActorKind $kind,
        public ?int $userId = null,
        public ?int $chatterId = null,
        public ?int $apiKeyId = null,
    ) {}
}
