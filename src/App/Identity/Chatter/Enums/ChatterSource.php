<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Enums;

/**
 * Where a chatter was created: `Dashboard` (invited from the dashboard) or
 * `Api` (created through the public REST API, `POST /api/v1/chatters`).
 */
enum ChatterSource: string
{
    case Dashboard = 'dashboard';
    case Api = 'api';
}
