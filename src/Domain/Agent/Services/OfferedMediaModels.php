<?php

declare(strict_types=1);

namespace Domain\Agent\Services;

use Domain\Agent\Data\ModelChoice\OfferedMediaModelData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Llm\Services\MediaModelCatalog;
use Illuminate\Support\Collection;

/**
 * The image and video models a chatter may pick in the hosted chat. The
 * admin lets agents offer a choice and ticks the models (Settings → AI
 * models), of the one service in use; the agent turns it on under Setup →
 * Model, with the capability itself on. The admin's model is always
 * offered, first, as the default. One model is no choice, so fewer than two
 * offer none. Audio files have no choice.
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
        return $this->models($kind, $this->offered($agent, $kind));
    }

    /**
     * The models the admin lets agents offer, whether or not this agent does:
     * what Setup → Model lists next to the agent's switch.
     *
     * @return list<OfferedMediaModelData>
     */
    public function choices(GeneratedMediaKind $kind): array
    {
        return $this->models($kind, $this->allowed($kind));
    }

    /** The picked model when the agent offers it; null otherwise (the admin's model is used). */
    public function resolve(Agent $agent, GeneratedMediaKind $kind, ?string $picked): ?string
    {
        return filled($picked) && $this->offered($agent, $kind)->contains($picked) ? $picked : null;
    }

    /** Whether the admin lets agents offer a choice of this kind, with two or more models to pick from. */
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

    /** A model's name for chatters. */
    public function name(GeneratedMediaKind $kind, string $model): string
    {
        return $kind === GeneratedMediaKind::Video ? $this->catalog->videoName($model) : $this->catalog->imageName($model);
    }

    /**
     * @return Collection<int, string>
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
     * @param  Collection<int, string>  $ids
     * @return list<OfferedMediaModelData>
     */
    private function models(GeneratedMediaKind $kind, Collection $ids): array
    {
        return $ids->map(fn (string $id): OfferedMediaModelData => new OfferedMediaModelData(
            id: $id,
            name: $this->name($kind, $id),
            is_default: $id === $ids->first(),
        ))->all();
    }

    /**
     * The admin's model, then the ones ticked that the service in use still
     * offers; empty while the admin has the choice off.
     *
     * @return Collection<int, string>
     */
    private function allowed(GeneratedMediaKind $kind): Collection
    {
        [$switchKey, $tickedKey, $listModels, $defaultModel] = match ($kind) {
            GeneratedMediaKind::Image => ['image_model_choice', 'image_models', $this->catalog->imageModels(...), $this->catalog->defaultImageModel(...)],
            GeneratedMediaKind::Video => ['video_model_choice', 'video_models', $this->catalog->videoModels(...), $this->catalog->defaultVideoModel(...)],
            GeneratedMediaKind::Audio => [null, null, null, null],
        };

        if ($switchKey === null || ! config("chatvia.ai.{$switchKey}")) {
            return collect();
        }

        $listed = collect($listModels())->keys();
        $ids = collect([$defaultModel(), ...(array) config("chatvia.ai.{$tickedKey}")])
            ->map(fn (mixed $id): string => (string) $id)
            ->filter(fn (string $id): bool => $listed->containsStrict($id))
            ->unique()
            ->values();

        return $ids->count() > 1 ? $ids : collect();
    }
}
