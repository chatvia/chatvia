<?php

namespace Domain\Conversation\Services\Turn;

use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Str;
use Spectra\Facades\Spectra;

/**
 * Tags a reply's AI call with the instructions the agent answered with, so
 * /spectra's Prompts screen compares them version by version on cost,
 * errors, latency and scores. The prompt is the attached library prompt, or
 * else the agent itself; the version is a hash of the instructions, so an
 * edit starts a new version and an undo returns to the old one. Spectra reads
 * the prompt when a request is sent, so wrap the call that sends a stream.
 */
class SpectraPromptTagger
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function during(Agent $agent, Closure $callback): mixed
    {
        if (! config('spectra.enabled')) {
            return $callback();
        }

        return Spectra::withPrompt(
            Str::limit($agent->agentPrompt->name ?? $agent->name, 100, ''),
            hash('crc32b', $agent->resolveSystemInstructions()),
            $callback,
        );
    }
}
