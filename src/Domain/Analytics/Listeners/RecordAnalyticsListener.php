<?php

namespace Domain\Analytics\Listeners;

use Domain\Analytics\Actions\RecordFeedbackGivenAction;
use Domain\Analytics\Actions\RecordMessageSentAction;
use Domain\Analytics\Events\FeedbackGivenEvent;
use Domain\Analytics\Events\MessageSentEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Throwable;

class RecordAnalyticsListener implements ShouldQueue
{
    public function __construct(
        private readonly RecordMessageSentAction $recordMessageSent,
        private readonly RecordFeedbackGivenAction $recordFeedbackGiven,
    ) {}

    public function viaQueue(): string
    {
        return (string) config('analytics.queue');
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageSentEvent::class => 'onMessageSent',
            FeedbackGivenEvent::class => 'onFeedbackGiven',
        ];
    }

    public function onMessageSent(MessageSentEvent $event): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        $this->recordMessageSent->execute($event);
    }

    public function onFeedbackGiven(FeedbackGivenEvent $event): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        $this->recordFeedbackGiven->execute($event);
    }

    public function failed(object $event, Throwable $exception): void
    {
        report($exception);
    }
}
