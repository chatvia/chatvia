<?php

namespace Tests\Support\Channel;

use Closure;
use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelAccessGate;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Services\Replies\StreamingReplyStrategy;
use Domain\Channel\Services\Replies\TypingReplyStrategy;
use Illuminate\Support\Carbon;

/**
 * Stand-in for {@see ChannelReplyRunner} so channel tests never run the real
 * chat pipeline (RAG + LLM). Records the turn it was asked to run, reports the
 * given partial replies as progress — moving the app clock forward before
 * each, so throttled strategies push every one — and returns a canned reply
 * (or echoes the message when none is given).
 */
class StubChannelReplyRunner extends ChannelReplyRunner
{
    public ?ChannelTurnData $turn = null;

    /**
     * @param  list<string>  $partials
     */
    public function __construct(
        private readonly ?string $reply = null,
        private readonly array $partials = [],
        private readonly int $secondsBetweenPartials = 60,
    ) {}

    public function reply(ChannelTurnData $turn, ?Closure $onProgress = null): string
    {
        $this->turn = $turn;

        foreach ($this->partials as $partial) {
            Carbon::setTestNow(now()->addSeconds($this->secondsBetweenPartials));

            if ($onProgress !== null) {
                $onProgress($partial);
            }
        }

        return $this->reply ?? $turn->message;
    }

    /** The shared channel turn, wired to this stub instead of the real pipeline. */
    public function handler(): HandleChannelMessageAction
    {
        return new HandleChannelMessageAction(
            new ChannelAccessGate,
            new TypingReplyStrategy($this),
            new StreamingReplyStrategy($this),
        );
    }

    /** The message the pipeline was asked to answer, or null when it never ran. */
    public function received(): ?string
    {
        return $this->turn?->message;
    }
}
