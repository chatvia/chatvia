<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Media;

use App\Identity\Workspace\Services\GenerationAllowance;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Data\Media\AttachedTextData;
use Domain\Conversation\Data\Media\MediaRequestData;
use Domain\Conversation\Data\Media\MediaRequestResultData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Enums\MediaRequestRefusal;
use Domain\Conversation\Jobs\GenerateMediaJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Media\AttachedFileText;
use Domain\Conversation\Services\Speech\ReadAloudAvailability;
use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Services\AudioGeneration;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Takes an agent's request for a picture, an audio file or a video in a chat. It is
 * turned down, with a reason the agent passes on, outside the hosted chat,
 * while the service is off (Settings → AI models), once the chat has had its
 * files for the hour, or once the workspace has used its monthly generation
 * limit (videos have a limit of their own). An audio file of the attached PDF reads the PDF's text here, and is
 * turned down when there is none, no text in it, or more than one file may
 * read. Otherwise the item is stored as pending, with the model the chatter
 * picked when the agent offers a choice ({@see OfferedMediaModels}), a slot
 * of the monthly limit is taken, and {@see GenerateMediaJob} makes the file
 * in the background.
 *
 * The tool call returns at once: a picture takes up to a minute and a video
 * several, longer than a reply should wait, and a reload must not lose it.
 */
class RequestMediaGenerationAction
{
    /** One hour, the window of the per-chat limit. */
    private const int HOUR = 3600;

    /** How much of a text the chat shows with its audio file. */
    private const int CAPTION = 200;

    public function __construct(
        private readonly ImageGenerator $images,
        private readonly AudioGeneration $audio,
        private readonly VideoGenerator $videos,
        private readonly ReadAloudAvailability $readAloud,
        private readonly AttachedFileText $attachedText,
        private readonly GenerationAllowance $allowance,
        private readonly OfferedMediaModels $offeredModels,
    ) {}

    public function execute(Agent $agent, Conversation $conversation, MediaRequestData $request): MediaRequestResultData
    {
        $refusal = $this->refusal($conversation, $request->kind);

        if ($refusal instanceof MediaRequestRefusal) {
            return MediaRequestResultData::refused($refusal);
        }

        $attached = null;

        if ($request->fromAttachment) {
            $attached = $this->attachedText->latestPdf($conversation);
            $refusal = $this->attachmentRefusal($attached);

            if ($refusal instanceof MediaRequestRefusal) {
                return MediaRequestResultData::refused($refusal);
            }
        }

        if (! $this->allowance->reserve($agent->workspace_id, $request->kind)) {
            return MediaRequestResultData::refused(MediaRequestRefusal::MonthlyLimit);
        }

        RateLimiter::hit($this->hourlyKey($conversation, $request->kind), self::HOUR);

        $audio = $request->kind === GeneratedMediaKind::Audio;
        $prompt = $attached instanceof AttachedTextData ? $attached->text : $request->prompt;
        // The chatter's pick from the header, while the agent still offers it.
        $model = $this->offeredModels->resolve($agent, $request->kind, $conversation->media_models[$request->kind->value] ?? null);
        $picked = $model !== null ? ['model' => $model, 'model_name' => $this->offeredModels->name($request->kind, $model)] : [];
        $media = GeneratedMedia::query()->create([
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
            'chat_conversation_id' => $conversation->id,
            'kind' => $request->kind,
            'status' => GeneratedMediaStatus::Pending,
            'prompt' => $prompt,
            'metadata' => match ($request->kind) {
                GeneratedMediaKind::Image => [
                    'shape' => ($request->shape instanceof ImageShape ? $request->shape : ImageShape::Square)->value,
                    ...$picked,
                ],
                // The admin's length and resolution as they are now, so a
                // change made while the job waits does not reach this video.
                GeneratedMediaKind::Video => [
                    'shape' => ($request->shape instanceof VideoShape ? $request->shape : VideoShape::Landscape)->value,
                    'seconds' => (int) config('chatvia.ai.video_duration'),
                    'resolution' => (VideoResolution::tryFrom((string) config('chatvia.ai.video_resolution')) ?? VideoResolution::Hd720)->value,
                    ...$picked,
                ],
                GeneratedMediaKind::Audio => ['voice' => $this->readAloud->voiceFor($agent, $this->audio->speaker)],
            },
            'service' => match ($request->kind) {
                GeneratedMediaKind::Image => $this->images->name($model),
                GeneratedMediaKind::Audio => $this->audio->name(),
                GeneratedMediaKind::Video => $this->videos->name($model),
            },
        ]);

        GenerateMediaJob::dispatch($media);

        return MediaRequestResultData::accepted($media->external_id, match (true) {
            ! $audio => $prompt,
            $attached instanceof AttachedTextData => $attached->filename,
            default => Str::limit($prompt, self::CAPTION),
        }, $picked['model_name'] ?? null);
    }

    private function refusal(Conversation $conversation, GeneratedMediaKind $kind): ?MediaRequestRefusal
    {
        return match (true) {
            $conversation->source !== ConversationSource::HostedChat => MediaRequestRefusal::WrongSurface,
            ! $this->isEnabled($kind) => MediaRequestRefusal::NotSetUp,
            RateLimiter::tooManyAttempts($this->hourlyKey($conversation, $kind), $this->perHour($kind)) => MediaRequestRefusal::HourlyLimit,
            default => null,
        };
    }

    private function attachmentRefusal(?AttachedTextData $attached): ?MediaRequestRefusal
    {
        return match (true) {
            ! $attached instanceof AttachedTextData => MediaRequestRefusal::NoAttachment,
            blank($attached->text) => MediaRequestRefusal::NoReadableText,
            Str::length($attached->text) > (int) config('chatvia.ai.audio_max_characters') => MediaRequestRefusal::TooLong,
            default => null,
        };
    }

    private function isEnabled(GeneratedMediaKind $kind): bool
    {
        return match ($kind) {
            GeneratedMediaKind::Image => $this->images->isEnabled(),
            GeneratedMediaKind::Audio => $this->audio->isEnabled(),
            GeneratedMediaKind::Video => $this->videos->isEnabled(),
        };
    }

    private function perHour(GeneratedMediaKind $kind): int
    {
        return max(1, (int) config(match ($kind) {
            GeneratedMediaKind::Image => 'chatvia.ai.images_per_chat_per_hour',
            GeneratedMediaKind::Audio => 'chatvia.ai.audio_per_chat_per_hour',
            GeneratedMediaKind::Video => 'chatvia.ai.videos_per_chat_per_hour',
        }));
    }

    private function hourlyKey(Conversation $conversation, GeneratedMediaKind $kind): string
    {
        return "generated-media:{$kind->value}:{$conversation->id}";
    }
}
