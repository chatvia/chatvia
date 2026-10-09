<?php

declare(strict_types=1);

namespace Domain\Widget;

/**
 * Names of the HTTP headers the embedded widget sends with every API call.
 *
 * They are the widget's wire contract: the widget script (a separate app)
 * hard-codes the same names, so a value here must never change on its own.
 * Every header carries a public external id, never an internal database id.
 */
final class WidgetHeaders
{
    /** External id of the agent the widget is embedded for. */
    public const string AGENT_ID = 'X-AGENT-ID';

    /** External id of the visitor's chat session. */
    public const string SESSION_ID = 'X-SESSION-ID';

    /** External id of the conversation the call is about. */
    public const string CONVERSATION_ID = 'X-CONVERSATION-ID';

    /** External id of the message a feedback call rates. */
    public const string MESSAGE_ID = 'X-MESSAGE-ID';

    /** Opaque widget session token for a protected agent (see the auth exchange). */
    public const string AUTH_TOKEN = 'X-AUTH-TOKEN';
}
