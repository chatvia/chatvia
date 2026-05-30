<?php

namespace Support\Enums;

/**
 * Where a user record was created from: `Dashboard` (any UI/console flow inside
 * the control panel — signup, team invite acceptance, admin command) or `Api`
 * (created through the public REST API, today only chatters via
 * `POST /api/v1/users`).
 */
enum UserSource: string
{
    case Dashboard = 'dashboard';
    case Api = 'api';
}
