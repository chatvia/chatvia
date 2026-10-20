<?php

declare(strict_types=1);

namespace App\Admin\Status\Data;

use App\Admin\Status\CheckMark;
use Spatie\LaravelData\Data;

/**
 * One line of a service card on the Status page: what was checked (a
 * version, a response time, an extension, a program), what was found, a
 * mark that judges it, a short tag such as "Optional", and a note under
 * the line on what it means (what stops working without a program).
 */
class ServiceCheckData extends Data
{
    public function __construct(
        public string $label,
        public string $value,
        public CheckMark $mark = CheckMark::Passed,
        public ?string $tag = null,
        public ?string $note = null,
    ) {}
}
