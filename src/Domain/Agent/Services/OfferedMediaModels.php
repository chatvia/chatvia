<?php

declare(strict_types=1);

namespace Domain\Agent\Services;

use Domain\Agent\Data\ModelChoice\OfferedMediaModelData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Llm\Data\Media\MediaModelData;
use Domain\Llm\Services\MediaModelCatalog;
use Illuminate\Support\Collection;

/**
 * The image and video models a chatter may pick in the hosted chat: every
 * model the admin listed (Settings → AI models), from one service or
 * several, with the admin's default first. The agent turns the choice on
 * under Setup → Model, with the capability itself on. One model is no
 * choice, so fewer than two offer none. Audio files have no choice. A model
 * is known by its `{service}:{model}` key ({@see MediaModelData}).
 */
final readonly class OfferedMediaModels
{
    /** The agent's switches, by kind (`image`, `video`). */
    public const string SETTINGS_GROUP = 'media_model_choice';

    public function __construct(
        private MediaModelCatalog $catalog,
    ) {}

    /**
     * @return list<OfferedMediaModelData>
     */
    public function for(Agent $agent, GeneratedMediaKind $kind): array
    {
        return $this->models($this->offered($agent, $kind));
    }

    /**
     * The models the admin lets agents offer, whether or not this agent does:
     * what Setup → Model lists next to the agent's switch.
     *
     * @return list<OfferedMediaModelData>
     */
    public function choices(GeneratedMediaKind $kind): array
    {
        return $this->models($this->allowed($kind));
    }

    /** The picked model when the agent offers it; null otherwise (the admin's default is used). */
    public function resolve(Agent $agent, GeneratedMediaKind $kind, ?string $picked): ?MediaModelData
    {
        return blank($picked) ? null : $this->offered($agent, $kind)->first(fn (MediaModelData $model): bool => $model->key() === $picked);
    }

    /** Whether the admin listed two or more models of this kind, so agents may offer a choice. */
    public function isAllowed(GeneratedMediaKind $kind): bool
    {
        return $this->allowed($kind)->isNotEmpty();
    }

    /** Whether the agent has the capability that makes this kind. */
    public function hasCapability(Agent $agent, GeneratedMediaKind $kind): bool
    {
        return $agent->mcpServers()->where('connector_slug', $kind->capabilitySlug())->exists();
    }

    /** Whether the agent has the choice of this kind turned on. */
    public function isEnabled(Agent $agent, GeneratedMediaKind $kind): bool
    {
        return $kind->offersModelChoice() && (bool) $agent->settings(self::SETTINGS_GROUP)->get($kind->value);
    }

    /**
     * @return Collection<int, MediaModelData>
     */
    private function offered(Agent $agent, GeneratedMediaKind $kind): Collection
    {
        if (! $this->isEnabled($agent, $kind)) {
            return collect();
        }

        $allowed = $this->allowed($kind);

        if ($allowed->isEmpty() || ! $this->hasCapability($agent, $kind)) {
            return collect();
        }

        return $allowed;
    }

    /**
     * @param  Collection<int, MediaModelData>  $models
     * @return list<OfferedMediaModelData>
     */
    private function models(Collection $models): array
    {
        return $models->map(fn (MediaModelData $model): OfferedMediaModelData => new OfferedMediaModelData(
            id: $model->key(),
            name: $model->name,
            is_default: $model === $models->first(),
        ))->values()->all();
    }

    /**
     * The admin's default, then the other models listed; empty when fewer
     * than two are.
     *
     * @return Collection<int, MediaModelData>
     */
    private function allowed(GeneratedMediaKind $kind): Collection
    {
        [$models, $default] = match ($kind) {
            GeneratedMediaKind::Image => [$this->catalog->imageModels(), $this->catalog->defaultImage()],
            GeneratedMediaKind::Video => [$this->catalog->videoModels(), $this->catalog->defaultVideo()],
            GeneratedMediaKind::Audio => [[], null],
        };

        if (count($models) < 2 || ! $default instanceof MediaModelData) {
            return collect();
        }

        return collect($models)
            ->sortBy(fn (MediaModelData $model): int => $model->key() === $default->key() ? 0 : 1)
            ->values();
    }
}
