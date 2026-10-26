<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Media;

use App\Identity\Workspace\Services\GenerationAllowance;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Media\MediaRequestData;
use Domain\Conversation\Data\Media\MediaRequestResultData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Enums\MediaRequestRefusal;
use Domain\Conversation\Jobs\GenerateMediaJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Speech\ReadAloudAvailability;
use Domain\Llm\Contracts\ImageGenerator;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Enums\ImageShape;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Takes an agent's request for a picture or an audio file in a chat. It is
 * turned down, with a reason the agent passes on, outside the hosted chat,
 * while the service is off (Settings → AI models), once the chat has had its
 * files for the hour, or once the workspace has used its monthly generation
 * limit. Otherwise the item is stored as pending, a slot of the monthly limit
 * is taken, and {@see GenerateMediaJob} makes the file in the background.
 *
 * The tool call returns at once: a picture takes up to a minute, longer than
 * a reply should wait, and a reload must not lose it.
 */
class RequestMediaGenerationAction
{
    /** One hour, the window of the per-chat limit. */
    private const int HOUR = 3600;

    public function __construct(
        private readonly ImageGenerator $images,
        private readonly Speaker $speaker,
        private readonly ReadAloudAvailability $readAloud,
        private readonly GenerationAllowance $allowance,
    ) {}

    public function execute(Agent $agent, Conversation $conversation, MediaRequestData $request): MediaRequestResultData
    {
        $refusal = $this->refusal($conversation, $request->kind);

        if ($refusal instanceof MediaRequestRefusal) {
            return MediaRequestResultData::refused($refusal);
        }

        if (! $this->allowance->reserve($agent->workspace_id)) {
            return MediaRequestResultData::refused(MediaRequestRefusal::MonthlyLimit);
        }

        RateLimiter::hit($this->hourlyKey($conversation, $request->kind), self::HOUR);

        $image = $request->kind === GeneratedMediaKind::Image;
        $media = GeneratedMedia::query()->create([
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
            'chat_conversation_id' => $conversation->id,
            'kind' => $request->kind,
            'status' => GeneratedMediaStatus::Pending,
            'prompt' => $request->prompt,
            'shape' => $image ? ($request->shape ?? ImageShape::Square)->value : null,
            'voice' => $image ? null : $this->readAloud->voiceFor($agent),
            'service' => $image ? $this->images->name() : $this->speaker->name(),
        ]);

        GenerateMediaJob::dispatch($media);

        return MediaRequestResultData::accepted($media->external_id);
    }

    private function refusal(Conversation $conversation, GeneratedMediaKind $kind): ?MediaRequestRefusal
    {
        return match (true) {
            $conversation->source !== ConversationSource::HostedChat => MediaRequestRefusal::WrongSurface,
            ! ($kind === GeneratedMediaKind::Image ? $this->images->isEnabled() : $this->speaker->isEnabled()) => MediaRequestRefusal::NotSetUp,
            RateLimiter::tooManyAttempts($this->hourlyKey($conversation, $kind), $this->perHour($kind)) => MediaRequestRefusal::HourlyLimit,
            default => null,
        };
    }

    private function perHour(GeneratedMediaKind $kind): int
    {
        return max(1, (int) config($kind === GeneratedMediaKind::Image ? 'chatvia.ai.images_per_chat_per_hour' : 'chatvia.ai.audio_per_chat_per_hour'));
    }

    private function hourlyKey(Conversation $conversation, GeneratedMediaKind $kind): string
    {
        return "generated-media:{$kind->value}:{$conversation->id}";
    }
}
