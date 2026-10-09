<?php

namespace Domain\Channel\Enums;

/**
 * Why a messaging channel could not be connected to an agent. The dashboard
 * turns each reason into its own field error.
 */
enum ChannelConnectFailure
{
    /** Channels serve public agents only; the agent is protected. */
    case ProtectedAgent;

    /** The bot / page / number / account already powers another agent. */
    case TakenByAnotherAgent;

    /** The platform rejected the credentials, or the connection could not be completed. */
    case Rejected;
}
