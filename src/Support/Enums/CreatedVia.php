<?php

declare(strict_types=1);

namespace Support\Enums;

/**
 * Where a record was created from: `Api` for records minted through the public
 * REST API, `Dashboard` for anything that came in through the control panel
 * (signup, console, admin commands, seeders).
 */
enum CreatedVia: string
{
    case Dashboard = 'dashboard';
    case Api = 'api';
}
