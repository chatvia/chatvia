<?php

declare(strict_types=1);

namespace Domain\Channel\Enums;

/**
 * Why a messaging channel could not be connected to an agent. The dashboard
 * turns each reason into its own field error.
 */
enum ChannelConnectFailure: string
{
    /** Channels serve public agents only; the agent is protected. */
    case ProtectedAgent = 'protected_agent';

    /** The bot / page / number / account already powers another agent. */
    case TakenByAnotherAgent = 'taken_by_another_agent';

    /** The platform rejected the credentials, or the connection could not be completed. */
    case Rejected = 'rejected';
}
