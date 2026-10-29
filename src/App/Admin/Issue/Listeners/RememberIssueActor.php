<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Data\IssueActorData;
use App\Admin\Issue\Enums\IssueActorKind;
use App\Admin\Issue\Services\IssueActor;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Events\Authenticated;
use Laravel\Sanctum\Events\TokenAuthenticated;

/**
 * Notes who is signed in for the request, the moment a guard knows them,
 * so an issue can name the person behind it ({@see IssueActor}): a staff
 * account, a chatter, or the API key a request came with.
 */
final class RememberIssueActor
{
    public function handle(Authenticated|TokenAuthenticated $event): void
    {
        $actor = match (true) {
            $event instanceof TokenAuthenticated => new IssueActorData(
                IssueActorKind::ApiKey,
                userId: $event->token->tokenable instanceof User ? (int) $event->token->tokenable->getKey() : null,
                apiKeyId: (int) $event->token->getKey(),
            ),
            $event->user instanceof Chatter => new IssueActorData(IssueActorKind::Chatter, chatterId: (int) $event->user->getKey()),
            $event->user instanceof User => new IssueActorData(IssueActorKind::Staff, userId: (int) $event->user->getKey()),
            default => null,
        };

        if ($actor instanceof IssueActorData) {
            IssueActor::remember($actor);
        }
    }
}
