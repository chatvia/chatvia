<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inbox;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The Inbox page: everyone chatting with the workspace's agents right now,
 * and the chat itself. The list loads from {@see InboxConversationController}.
 */
class InboxController
{
    public function index(): Response
    {
        return Inertia::render('inbox/Index');
    }
}
