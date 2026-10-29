<?php

declare(strict_types=1);

namespace App\Admin\Issue\Services;

use App\Admin\Issue\Data\IssueActorData;
use App\Admin\Issue\Enums\IssueActorKind;
use App\Admin\Issue\Enums\IssueOriginKind;
use App\Admin\Issue\Listeners\RememberIssueActor;
use Illuminate\Support\Facades\Context;

/**
 * Who is behind what the app is doing now. The person is noted in hidden
 * Context when they are signed in
 * ({@see RememberIssueActor}); Laravel carries
 * that Context into every job the request queues, so an issue inside such
 * a job still names them. A request with nobody signed in is a visitor's;
 * a job or command with nobody behind it is the app's own.
 */
final class IssueActor
{
    private const string CONTEXT_KEY = 'issue_actor';

    public static function remember(IssueActorData $actor): void
    {
        Context::addHidden(self::CONTEXT_KEY, $actor->toArray());
    }

    public function current(IssueOriginKind $origin): IssueActorData
    {
        $remembered = Context::getHidden(self::CONTEXT_KEY);

        if (is_array($remembered)) {
            return IssueActorData::from($remembered);
        }

        return new IssueActorData($origin === IssueOriginKind::Request ? IssueActorKind::Visitor : IssueActorKind::System);
    }
}
