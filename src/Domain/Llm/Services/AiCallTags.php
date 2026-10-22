<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Closure;
use Domain\Llm\Enums\AiCallTag;
use Spectra\Facades\Spectra;

/**
 * Tags the AI calls sent inside a callback with what they are for, so an
 * admin can tell them apart in /spectra. The tag holds only for the
 * callback, so one request or job can tag each of its calls on its own;
 * nested calls carry the outer tags too (an evaluation's replies are
 * `evaluation` and `chat-reply`). Spectra reads the tags when a request is
 * sent, so wrap the call that sends a stream, not the code that reads it.
 * While Spectra does not record (`spectra.enabled`), it only runs the
 * callback.
 */
final class AiCallTags
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function during(AiCallTag $tag, Closure $callback): mixed
    {
        if (! config('spectra.enabled')) {
            return $callback();
        }

        return Spectra::withTags([$tag->value], $callback);
    }
}
