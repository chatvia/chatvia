<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The recording formats a chat voice note may come in: what the browsers'
 * recorders write (WebM in Chrome, Edge and Firefox, MP4 in Safari) plus the
 * common audio files. The format is taken from the file's detected type, never
 * from its name, and it names both the working copy's extension and the
 * demuxer ffmpeg is told to use, so a crafted upload (a playlist, a concat
 * list) is never read as anything but this audio format.
 */
enum VoiceNoteFormat: string
{
    case Webm = 'webm';
    case Ogg = 'ogg';
    case Mp4 = 'mp4';
    case Mp3 = 'mp3';
    case Wav = 'wav';

    /**
     * The detected types (finfo) of this format. An audio-only WebM or MP4 is
     * often detected as video.
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Webm => ['audio/webm', 'video/webm'],
            self::Ogg => ['audio/ogg', 'application/ogg', 'audio/opus'],
            self::Mp4 => ['audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/m4a'],
            self::Mp3 => ['audio/mpeg', 'audio/mp3'],
            self::Wav => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        };
    }

    /**
     * The file name endings a voice note of this format may carry.
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Webm => ['webm'],
            self::Ogg => ['ogg', 'oga', 'opus'],
            self::Mp4 => ['mp4', 'm4a'],
            self::Mp3 => ['mp3', 'mpeg', 'mpga'],
            self::Wav => ['wav'],
        };
    }

    /** The extension the working copy gets; the speech-to-text service reads the format from it. */
    public function extension(): string
    {
        return $this->value;
    }

    /** The ffmpeg demuxer (`-f`) for this format. */
    public function demuxer(): string
    {
        return match ($this) {
            self::Webm => 'webm',
            self::Ogg => 'ogg',
            self::Mp4 => 'mp4',
            self::Mp3 => 'mp3',
            self::Wav => 'wav',
        };
    }

    /** The format of a detected type, or null for any other file. */
    public static function fromMimeType(?string $mimeType): ?self
    {
        $mimeType = Str::lower($mimeType ?? '');

        return Arr::first(self::cases(), fn (self $format): bool => collect($format->mimeTypes())->containsStrict($mimeType));
    }

    /**
     * Every detected type a voice note may have.
     *
     * @return list<string>
     */
    public static function allMimeTypes(): array
    {
        return collect(self::cases())->flatMap(fn (self $format): array => $format->mimeTypes())->values()->all();
    }

    /**
     * Every file name ending a voice note may carry.
     *
     * @return list<string>
     */
    public static function allExtensions(): array
    {
        return collect(self::cases())->flatMap(fn (self $format): array => $format->extensions())->values()->all();
    }
}
