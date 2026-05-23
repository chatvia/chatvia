<?php

namespace App\Services\Chat\Streaming;

use App\Services\Chat\Streaming\Exceptions\ClientDisconnected;
use Generator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SseStreamer
{
    /**
     * Wrap a generator of StreamEvents into a Symfony StreamedResponse.
     *
     * Owns: header setup, output-buffer cleanup, flushing, seq numbering,
     * and client-disconnect detection. This is the only place in the app
     * that should construct a text/event-stream response.
     *
     * @param  iterable<StreamEvent>  $events  the source generator/iterable
     */
    public function respond(iterable $events): StreamedResponse
    {
        return new StreamedResponse(
            fn () => $this->pump($events),
            200,
            $this->headers()
        );
    }

    private function pump(iterable $events): void
    {
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(false);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $seq = 0;

        if ($events instanceof Generator) {
            $this->pumpGenerator($events, $seq);

            return;
        }

        foreach ($events as $event) {
            if (! $this->emit($event, $seq)) {
                return;
            }
            $seq++;
        }
    }

    private function pumpGenerator(Generator $events, int &$seq): void
    {
        try {
            while ($events->valid()) {
                /** @var StreamEvent $event */
                $event = $events->current();

                if (! $this->emit($event, $seq)) {
                    $events->throw(new ClientDisconnected());

                    return;
                }
                $seq++;
                $events->next();
            }
        } catch (ClientDisconnected) {
            // Generator handled the disconnect itself; nothing else to do.
        } catch (Throwable $e) {
            Log::error('SSE stream generator crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    private function emit(StreamEvent $event, int $seq): bool
    {
        if (connection_status() !== CONNECTION_NORMAL || connection_aborted()) {
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

        return connection_status() === CONNECTION_NORMAL && ! connection_aborted();
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
