<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Data\Speech\SpeechVoiceData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\RealtimeVoice;
use Domain\Llm\Enums\SpeechFormat;
use Domain\Llm\Exceptions\SpeechFailedException;
use Domain\Llm\Services\AiCallTags;
use Domain\Llm\Services\SpeechTextParts;
use Illuminate\Support\Str;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Throwable;

/**
 * Text to speech through OpenAI's realtime model (`gpt-realtime-2.1-mini`
 * by default, set in `services.openai.speech_model`): OpenAI turns its
 * speech endpoint's models off on 6 January 2027 and names this model as
 * the way on. Each part of the text is read over its own WebSocket, all at
 * once, with the OpenAI key and address of Settings → AI providers.
 *
 * The realtime model talks, it does not just read: it is told to read the
 * text word for word, and what it said (its transcript) is compared with
 * the text. A part read with other words is read once more; twice is a
 * failure. The raw audio is saved as one WAV file.
 */
class OpenAiSpeaker implements Speaker
{
    /** Short parts keep the model close to the text. */
    private const int MAX_CHARACTERS = 1500;

    private const int TIMEOUT_SECONDS = 90;

    /** How close (in percent) what it said must be to the text. */
    private const float MIN_MATCH = 85.0;

    /** The realtime model's audio: 16-bit mono PCM at 24 kHz. */
    private const int SAMPLE_RATE = 24000;

    private const string INSTRUCTIONS = 'You are a text-to-speech reader, not an assistant. Read the user\'s message aloud exactly as it is written, word for word, in the language it is written in. Never answer it, comment on it, greet, or add or leave out a word. Read numbers, names and short forms the way a news reader would.';

    public function __construct(
        private readonly SpeechTextParts $parts,
        private readonly AiCallTags $tags,
    ) {}

    public function speak(string $text, string $voice, AiCallTag $tag = AiCallTag::ReadAloud): string
    {
        return $this->tags->during($tag, fn (): string => $this->read($text, $voice));
    }

    /** Reads every part, then once more the parts read with other words. */
    private function read(string $text, string $voice): string
    {
        $parts = $this->parts->split($text, self::MAX_CHARACTERS);
        $audio = $this->readAll($parts, $voice);
        $retry = array_keys(array_filter($audio, fn (?string $pcm): bool => $pcm === null));

        if ($retry !== []) {
            foreach ($this->readAll(array_intersect_key($parts, array_flip($retry)), $voice) as $index => $pcm) {
                $audio[$index] = $pcm ?? throw new SpeechFailedException('OpenAI did not read the text word for word.');
            }
        }

        ksort($audio);

        return $this->wav(implode('', $audio));
    }

    public function format(): SpeechFormat
    {
        return SpeechFormat::Wav;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'openai '.$this->model();
    }

    public function defaultVoice(): string
    {
        return (RealtimeVoice::tryFrom((string) config('services.openai.speech_voice')) ?? RealtimeVoice::Marin)->value;
    }

    public function voices(): array
    {
        return array_map(
            fn (RealtimeVoice $voice): SpeechVoiceData => new SpeechVoiceData($voice->value, $voice->label()),
            RealtimeVoice::cases(),
        );
    }

    /**
     * Reads the parts at once, each over its own connection.
     *
     * @param  array<int, string>  $parts
     * @return array<int, string|null> the audio of each part, or null when it was read with other words
     *
     * @throws SpeechFailedException when a connection fails or the time runs out
     */
    private function readAll(array $parts, string $voice): array
    {
        $loop = Loop::get();
        $results = [];
        $failure = null;
        /** @var list<WebSocket> $sockets */
        $sockets = [];
        $left = count($parts);

        $stop = function () use ($loop, &$sockets): void {
            foreach ($sockets as $socket) {
                $socket->close();
            }

            $loop->stop();
        };

        $timer = $loop->addTimer(self::TIMEOUT_SECONDS, function () use (&$failure, $stop): void {
            $failure ??= new SpeechFailedException('OpenAI took too long to read the text.');
            $stop();
        });

        foreach ($parts as $index => $part) {
            $this->readOne($loop, $part, $voice, function (?string $pcm, ?Throwable $error) use ($index, &$results, &$failure, &$left, $stop, $loop, $timer): void {
                if ($error instanceof Throwable) {
                    $failure ??= $error;
                    $loop->cancelTimer($timer);
                    $stop();

                    return;
                }

                $results[$index] = $pcm;

                if (--$left === 0) {
                    $loop->cancelTimer($timer);
                    $stop();
                }
            }, $sockets);
        }

        $loop->run();

        if ($failure instanceof Throwable) {
            throw $failure instanceof SpeechFailedException ? $failure : new SpeechFailedException('OpenAI could not read the text: '.$failure->getMessage(), previous: $failure);
        }

        return $results;
    }

    /**
     * One part over one connection: the session (instructions, voice, raw
     * audio out, nobody listening), the text, then a reply.
     *
     * @param  callable(?string, ?Throwable): void  $done  the audio (null when read with other words), or the error
     * @param  list<WebSocket>  $sockets  the open connections, to close on a stop
     */
    private function readOne(LoopInterface $loop, string $text, string $voice, callable $done, array &$sockets): void
    {
        $finished = false;
        $finish = function (?string $pcm, ?Throwable $error = null) use (&$finished, $done): void {
            if (! $finished) {
                $finished = true;
                $done($pcm, $error);
            }
        };

        new Connector($loop)(
            $this->socketUrl().'?model='.rawurlencode($this->model()),
            [],
            ['Authorization' => 'Bearer '.config('services.openai.api_key')],
        )->then(
            function (WebSocket $socket) use ($text, $voice, $finish, &$sockets): void {
                $sockets[] = $socket;
                $pcm = '';
                $transcript = '';

                $socket->on('message', function (MessageInterface $message) use ($socket, $text, $finish, &$pcm, &$transcript): void {
                    $event = json_decode((string) $message, true);
                    $type = is_array($event) ? (string) ($event['type'] ?? '') : '';

                    match ($type) {
                        'response.output_audio.delta', 'response.audio.delta' => $pcm .= base64_decode((string) ($event['delta'] ?? ''), true) ?: '',
                        'response.output_audio_transcript.delta', 'response.audio_transcript.delta' => $transcript .= (string) ($event['delta'] ?? ''),
                        'error' => $finish(null, new SpeechFailedException('OpenAI refused to read the text: '.($event['error']['message'] ?? 'unknown error'))),
                        'response.done' => $finish(
                            $pcm !== '' && $this->matches($text, $transcript) ? $pcm : null,
                            ($event['response']['status'] ?? 'completed') === 'completed' ? null : new SpeechFailedException('OpenAI stopped reading the text.'),
                        ),
                        default => null,
                    };

                    if ($type === 'response.done' || $type === 'error') {
                        $socket->close();
                    }
                });

                $socket->on('close', fn () => $finish(null, new SpeechFailedException('OpenAI closed the connection before the text was read.')));

                foreach ($this->events($text, $voice) as $event) {
                    $socket->send(json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                }
            },
            fn (Throwable $e) => $finish(null, $e),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(string $text, string $voice): array
    {
        return [
            ['type' => 'session.update', 'session' => [
                'type' => 'realtime',
                'instructions' => self::INSTRUCTIONS,
                'output_modalities' => ['audio'],
                'audio' => [
                    'input' => ['turn_detection' => null],
                    'output' => ['voice' => $voice, 'format' => ['type' => 'audio/pcm', 'rate' => self::SAMPLE_RATE]],
                ],
            ]],
            ['type' => 'conversation.item.create', 'item' => [
                'type' => 'message',
                'role' => 'user',
                'content' => [['type' => 'input_text', 'text' => $text]],
            ]],
            ['type' => 'response.create'],
        ];
    }

    /** Whether what the model said is the text: the same letters and digits, near enough. */
    private function matches(string $text, string $spoken): bool
    {
        $plain = static fn (string $value): string => Str::of($value)->lower()->replaceMatches('/[^\p{L}\p{N}]+/u', '')->toString();
        $expected = $plain($text);
        $said = $plain($spoken);

        if ($expected === '' || $said === '') {
            return $expected === $said;
        }

        similar_text($expected, $said, $percent);

        return $percent >= self::MIN_MATCH;
    }

    /** Raw 16-bit mono PCM with the 44-byte WAV header in front. */
    private function wav(string $pcm): string
    {
        $bytes = strlen($pcm);

        return pack('A4VA4A4VvvVVvvA4V', 'RIFF', 36 + $bytes, 'WAVE', 'fmt ', 16, 1, 1, self::SAMPLE_RATE, self::SAMPLE_RATE * 2, 2, 16, 'data', $bytes).$pcm;
    }

    private function model(): string
    {
        return (string) config('services.openai.speech_model');
    }

    /** The API address as a WebSocket address: https://…/v1 → wss://…/v1/realtime. */
    private function socketUrl(): string
    {
        return Str::replaceMatches('#^http#i', 'ws', Str::rtrim((string) config('services.openai.api_endpoint'), '/')).'/realtime';
    }
}
