<?php

declare(strict_types=1);

namespace App\Admin\Issue\Enums;

/** How a resolved issue was closed: by an admin, or by the service working again. */
enum IssueResolution: string
{
    case Admin = 'admin';
    case Recovered = 'recovered';
}
