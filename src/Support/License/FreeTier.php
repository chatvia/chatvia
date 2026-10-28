<?php

declare(strict_types=1);

namespace Support\License;

/**
 * The free-use limit of an install without a license key (condition 4 of
 * the ChatVia.ai License): one workspace. It is fixed here, not a setting,
 * because the license is what allows going past it; any valid key lifts it.
 * Agents, teams and replies have no free limit. The switches (API, voice,
 * capabilities and forms, splitting) need no number, so they are read
 * through Entitlements alone.
 */
final class FreeTier
{
    /** Workspaces an install may hold. */
    public const int WORKSPACES = 1;
}
