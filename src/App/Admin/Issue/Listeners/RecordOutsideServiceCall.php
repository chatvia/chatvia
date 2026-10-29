<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Services\OutsideCallInspector;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;

/**
 * Shows every call made with Laravel's HTTP client to
 * {@see OutsideCallInspector}: a refusal an admin can fix becomes an issue,
 * a success closes the service's open ones. Swallows its own failures, so
 * the call is never affected.
 */
final readonly class RecordOutsideServiceCall
{
    public function __construct(private OutsideCallInspector $inspector) {}

    public function handle(ResponseReceived|ConnectionFailed $event): void
    {
        rescue(fn () => $event instanceof ResponseReceived
            ? $this->inspector->answered($event->request->url(), $event->request->isJson() ? $event->request->data() : [], $event->response)
            : $this->inspector->unreachable($event->request->url(), $event->exception->getMessage()), report: false);
    }
}
