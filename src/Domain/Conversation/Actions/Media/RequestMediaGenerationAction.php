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
use Domain\Conversation\Services\Media\HourlyMediaLimit;
use Domain\Conversation\Services\Speech\ReadAloudAvailability;
use Domain\Llm\Data\Media\MediaModelData;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Services\AudioGeneration;
use Domain\Llm\Services\MediaModelCatalog;
use Illuminate\Support\Str;

/**
 * Takes an agent's request for a picture, an audio file or a video in a chat. It is
 * turned down, with a reason the agent passes on, outside the hosted chat,
 * while no model is listed (Settings → AI models), once the chat has had its
 * files for the hour ({@see HourlyMediaLimit}), or once the workspace has
 * used its monthly generation limit (videos have a limit of their own). An
 * audio file of the attached PDF reads the PDF's text here, and is turned
 * down when there is none, no text in it, or more than one file may read.
 * Otherwise the item is stored as pending, with the service and model that
 * make it: the one the chatter picked when the agent offers a choice
 * ({@see OfferedMediaModels}), else the admin's default. A slot
 * of the hourly and the monthly limit is taken (both given back if making
 * it fails), and {@see GenerateMediaJob} makes the file in the background.
 *
 * The tool call returns at once: a picture takes up to a minute and a video
 * several, longer than a reply should wait, and a reload must not lose it.
 */
class RequestMediaGenerationAction
{
    /** How much of a text the chat shows with its audio file. */
    private const int CAPTION = 200;

    public function __construct(
        private readonly MediaModelCatalog $catalog,
        private readonly AudioGeneration $audio,
        private readonly ReadAloudAvailability $readAloud,
        private readonly AttachedFileText $attachedText,
        private readonly GenerationAllowance $allowance,
        private readonly OfferedMediaModels $offeredModels,
        private readonly HourlyMediaLimit $hourly,
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

        $this->hourly->take($conversation, $request->kind);

        $audio = $request->kind === GeneratedMediaKind::Audio;
        $prompt = $attached instanceof AttachedTextData ? $attached->text : $request->prompt;
        // The chatter's pick from the header, while the agent still offers it.
        $picked = $this->offeredModels->resolve($agent, $request->kind, $conversation->media_models[$request->kind->value] ?? null);
        $model = $picked ?? $this->defaultModel($request->kind);
        $made = $model instanceof MediaModelData ? [
            'model' => $model->model,
            ...($picked instanceof MediaModelData ? ['model_name' => $picked->name] : []),
        ] : [];
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
                    ...$made,
                ],
                // The admin's length and resolution as they are now, so a
                // change made while the job waits does not reach this video.
                GeneratedMediaKind::Video => [
                    'shape' => ($request->shape instanceof VideoShape ? $request->shape : VideoShape::Landscape)->value,
                    'seconds' => (int) config('chatvia.ai.video_duration'),
                    'resolution' => (VideoResolution::tryFrom((string) config('chatvia.ai.video_resolution')) ?? VideoResolution::Hd720)->value,
                    ...$made,
                ],
                GeneratedMediaKind::Audio => ['voice' => $this->readAloud->voiceFor($agent, $this->audio->speaker)],
            },
            // The service the job and each video check go to, whatever the
            // admin lists by then.
            'service' => $model instanceof MediaModelData ? $model->service->value : $this->audio->name(),
        ]);

        GenerateMediaJob::dispatch($media);

        return MediaRequestResultData::accepted($media->external_id, match (true) {
            ! $audio => $prompt,
            $attached instanceof AttachedTextData => $attached->filename,
            default => Str::limit($prompt, self::CAPTION),
        }, $picked?->name);
    }

    private function refusal(Conversation $conversation, GeneratedMediaKind $kind): ?MediaRequestRefusal
    {
        return match (true) {
            $conversation->source !== ConversationSource::HostedChat => MediaRequestRefusal::WrongSurface,
            ! $this->isEnabled($kind) => MediaRequestRefusal::NotSetUp,
            $this->hourly->isReached($conversation, $kind) => MediaRequestRefusal::HourlyLimit,
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
            GeneratedMediaKind::Image => $this->catalog->imageEnabled(),
            GeneratedMediaKind::Audio => $this->audio->isEnabled(),
            GeneratedMediaKind::Video => $this->catalog->videoEnabled(),
        };
    }

    private function defaultModel(GeneratedMediaKind $kind): ?MediaModelData
    {
        return match ($kind) {
            GeneratedMediaKind::Image => $this->catalog->defaultImage(),
            GeneratedMediaKind::Video => $this->catalog->defaultVideo(),
            GeneratedMediaKind::Audio => null,
        };
    }
}
