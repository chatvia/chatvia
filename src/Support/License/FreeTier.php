<?php

declare(strict_types=1);

namespace Support\License;

/**
 * The free-use limits of an install without a license key (condition 4 of
 * the ChatVia.ai License). They are fixed here, not settings, because the
 * license is what allows going past them; any valid key lifts them all.
 * The switches (API, voice, capabilities and forms, splitting) need no
 * number, so they are read through Entitlements alone.
 */
final class FreeTier
{
    /** Workspaces an install may hold. */
    public const int WORKSPACES = 1;

    /** Agents a workspace may hold, deleted agents not counted. */
    public const int AGENTS = 10;

    /** Teams a workspace may hold: the one it starts with. */
    public const int TEAMS = 1;

    /** Agent replies a workspace may send in a calendar month. */
    public const int MONTHLY_MESSAGES = 10_000;
}
