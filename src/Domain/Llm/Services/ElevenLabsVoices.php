<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Data\Speech\SpeechVoiceData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The voices of the ElevenLabs account, read from `/voices` and cached for
 * an hour per key (empty, and not cached, when the call fails). No voice id
 * is built in: ElevenLabs retires voices (its old default voices end on 31
 * December 2026), so the list always comes from the account.
 *
 * {@see all()} is every voice, for the admin's pick; {@see recommended()} is
 * the few an agent chooses from: voices ElevenLabs labels for professional
 * use (narration, news, teaching, conversation) that speak the most
 * languages.
 */
final class ElevenLabsVoices
{
    /** How many voices an agent chooses from. */
    public const int RECOMMENDED = 8;

    /** ElevenLabs' `use_case` labels of a voice fit for a business reply. */
    private const array PROFESSIONAL_USES = ['narration', 'news', 'informative_educational', 'conversational', 'audiobook', 'narrative_story', 'professional'];

    private const int CACHE_SECONDS = 3600;

    /** @return list<SpeechVoiceData> */
    public function all(): array
    {
        return array_map(fn (array $voice): SpeechVoiceData => new SpeechVoiceData($voice['id'], $voice['label']), $this->voices());
    }

    /**
     * Professional voices first, those verified in more languages before
     * the others; every voice when the account has no professional one.
     *
     * @return list<SpeechVoiceData>
     */
    public function recommended(): array
    {
        $voices = collect($this->voices());
        $professional = $voices->filter(fn (array $voice): bool => $voice['professional']);

        return ($professional->isNotEmpty() ? $professional : $voices)
            ->sortByDesc(fn (array $voice): int => $voice['languages'])
            ->take(self::RECOMMENDED)
            ->map(fn (array $voice): SpeechVoiceData => new SpeechVoiceData($voice['id'], $voice['label']))
            ->values()
            ->all();
    }

    /** @return list<array{id: string, label: string, professional: bool, languages: int}> */
    private function voices(): array
    {
        $key = (string) config('services.elevenlabs.api_key');

        if (blank($key)) {
            return [];
        }

        $cacheKey = 'speech:elevenlabs-voices:'.hash('sha256', $key);
        $voices = Cache::get($cacheKey);

        if (! is_array($voices)) {
            $voices = $this->fetch($key);

            if ($voices !== []) {
                Cache::put($cacheKey, $voices, self::CACHE_SECONDS);
            }
        }

        return $voices;
    }

    /** @return list<array{id: string, label: string, professional: bool, languages: int}> */
    private function fetch(string $key): array
    {
        try {
            $voices = Http::withHeaders(['xi-api-key' => $key])
                ->acceptJson()
                ->timeout(15)
                ->get(Str::rtrim((string) config('services.elevenlabs.endpoint'), '/').'/voices')
                ->throw()
                ->json('voices', []);
        } catch (Throwable) {
            return [];
        }

        return collect(is_array($voices) ? $voices : [])
            ->filter(fn (mixed $voice): bool => is_array($voice) && filled($voice['voice_id'] ?? null))
            ->map(fn (array $voice): array => [
                'id' => (string) $voice['voice_id'],
                'label' => $this->label($voice),
                'professional' => in_array(Str::lower((string) ($voice['labels']['use_case'] ?? '')), self::PROFESSIONAL_USES, true),
                'languages' => collect(Arr::wrap($voice['verified_languages'] ?? null))->pluck('language')->filter()->unique()->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * "Sarah (American, narration)": the name with the accent and use
     * ElevenLabs labels it with, so voices can be told apart in a list.
     *
     * @param  array<string, mixed>  $voice
     */
    private function label(array $voice): string
    {
        $name = (string) ($voice['name'] ?? $voice['voice_id']);
        $traits = collect([$voice['labels']['accent'] ?? null, $voice['labels']['use_case'] ?? null])
            ->filter(fn (mixed $trait): bool => filled($trait))
            ->map(fn (mixed $trait): string => Str::of((string) $trait)->replace('_', ' ')->ucfirst()->toString())
            ->implode(', ');

        return $traits === '' ? $name : "{$name} ({$traits})";
    }
}
