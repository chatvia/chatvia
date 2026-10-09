<?php

namespace App\Http\Controllers\Widget\Concerns;

use App\Http\Middleware\Widget\EnsureWidgetIsAccessible;
use App\Http\Widget\WidgetAgent;
use App\Identity\Workspace\Models\User;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Typed access to what every widget API call carries: the visitor's session
 * and conversation ids (headers, see {@see WidgetHeaders}) and the signed-in
 * chatter that {@see EnsureWidgetIsAccessible} attached. The agent itself is
 * injected with {@see WidgetAgent}.
 *
 * Error texts in widget responses are read by the widget script, not shown to
 * people, so they stay plain English.
 */
trait InteractsWithWidgetRequest
{
    /**
     * The visitor's session id. Session ids are UUIDs, so any other value is
     * treated as no session at all (it could never match a stored session).
     */
    protected function widgetSessionId(Request $request): ?string
    {
        $sessionId = $this->widgetHeader($request, WidgetHeaders::SESSION_ID);

        return $sessionId !== null && Str::isUuid($sessionId) ? $sessionId : null;
    }

    /**
     * The conversation id header as sent. It is NOT reduced to null when it is
     * malformed: on "clear chat" a missing header means "every conversation",
     * so a bad id must fail the ownership check instead.
     */
    protected function widgetConversationId(Request $request): ?string
    {
        return $this->widgetHeader($request, WidgetHeaders::CONVERSATION_ID);
    }

    /** The signed-in chatter, or null for an anonymous visitor. */
    protected function widgetUser(Request $request): ?User
    {
        $user = $request->attributes->get(EnsureWidgetIsAccessible::USER_ATTRIBUTE);

        return $user instanceof User ? $user : null;
    }

    private function widgetHeader(Request $request, string $name): ?string
    {
        $value = $request->header($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
