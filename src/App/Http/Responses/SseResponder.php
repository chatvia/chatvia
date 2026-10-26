<?php

namespace App\Http\Responses;

use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Generator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Streams a chat turn's {@see StreamEvent}s to the browser as server-sent
 * events. The domain only yields events; this is the one place that owns the
 * transport: the `text/event-stream` headers, output-buffer cleanup,
 * flushing, sequence numbers, and noticing when the client went away (the
 * event generator is then told with {@see ClientDisconnected}, so it can keep
 * a partial answer).
 *
 * Resolved from the container so tests can swap in a buffer-friendly copy.
 */
class SseResponder
{
    /**
     * @param  iterable<StreamEvent>  $events
     */
    public function respond(iterable $events): StreamedResponse
    {
        return new StreamedResponse(fn () => $this->pump($events), 200, $this->headers());
    }

    /**
     * Like {@see respond()}, but a browser that goes away (a reload, a closed
     * tab) does not end the turn: writing stops, the events are read to the
     * end, and the turn finishes and saves its whole reply as usual. The
     * hosted chat uses this; a reloaded chat picks the reply up, and its Stop
     * button ends a turn through its own endpoint instead.
     *
     * @param  iterable<StreamEvent>  $events
     */
    public function respondUntilFinished(iterable $events): StreamedResponse
    {
        return new StreamedResponse(function () use ($events): void {
            $this->prepare();
            $this->pumpToEnd($events);
        }, 200, $this->headers());
    }

    /**
     * @param  iterable<StreamEvent>  $events
     */
    private function pump(iterable $events): void
    {
        $this->prepare();

        if ($events instanceof Generator) {
            $this->pumpGenerator($events);

            return;
        }

        $seq = 0;

        foreach ($events as $event) {
            if (! $this->emit($event, $seq++)) {
                return;
            }
        }
    }

    /**
     * Send the events while the browser listens, then keep reading them
     * without sending until the turn is done.
     *
     * @param  iterable<StreamEvent>  $events
     */
    private function pumpToEnd(iterable $events): void
    {
        $seq = 0;
        $listening = true;

        try {
            foreach ($events as $event) {
                if ($listening) {
                    $listening = $this->emit($event, $seq++);
                }
            }
        } catch (Throwable $e) {
            Log::error('SSE stream generator crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /** Ready the response to stream: no time limit, no abort, no buffers. */
    private function prepare(): void
    {
        // Long tool chains (fetch data → render a chart → write the answer)
        // routinely run past the PHP-FPM 30s default. The client-disconnect
        // check in emit() is the real end signal here, not the time limit.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $this->keepRunningAfterDisconnect();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private function pumpGenerator(Generator $events): void
    {
        $seq = 0;

        try {
            while ($events->valid()) {
                if (! $this->emit($events->current(), $seq++)) {
                    $events->throw(new ClientDisconnected);

                    return;
                }

                $events->next();
            }
        } catch (ClientDisconnected) {
            // The generator handled the disconnect itself; nothing else to do.
        } catch (Throwable $e) {
            Log::error('SSE stream generator crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Keep running after the browser goes away (a reload, a closed tab). Left
     * to abort, PHP stops the script inside the failed flush(), before emit()
     * can tell the generator, so the turn's reply (and any file a tool already
     * asked for) would never be saved and a reload would ask for the same
     * answer again. emit() still sees the disconnect, and the generator ends
     * the turn there.
     */
    private function keepRunningAfterDisconnect(): void
    {
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
    }

    /** Write one event; false once the client is gone. */
    private function emit(StreamEvent $event, int $seq): bool
    {
        if ($this->clientGone()) {
            return false;
        }

        try {
            echo $event->withSeq($seq)->toLine();
        } catch (Throwable $e) {
            Log::error('SSE envelope encoding failed', [
                'type' => $event->type->value,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return true;
        }

        flush();

        return ! $this->clientGone();
    }

    /**
     * Whether the browser went away; a test overrides it to fake that.
     *
     * @phpstan-impure
     */
    protected function clientGone(): bool
    {
        return connection_status() !== CONNECTION_NORMAL || connection_aborted();
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }
}
