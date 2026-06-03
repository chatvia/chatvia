<?php

namespace Domain\Analytics\Listeners;

use Domain\Analytics\Actions\RecordFeedbackGivenAction;
use Domain\Analytics\Actions\RecordMessageSentAction;
use Domain\Analytics\Events\FeedbackGiven;
use Domain\Analytics\Events\MessageSent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Throwable;

class RecordAnalytics implements ShouldQueue
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
            MessageSent::class => 'onMessageSent',
            FeedbackGiven::class => 'onFeedbackGiven',
        ];
    }

    public function onMessageSent(MessageSent $event): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        $this->recordMessageSent->execute($event);
    }

    public function onFeedbackGiven(FeedbackGiven $event): void
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
