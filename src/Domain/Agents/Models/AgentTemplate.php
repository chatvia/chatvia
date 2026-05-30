<?php

namespace Domain\Agents\Models;

use App\Auth\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Support\Concerns\HasExternalId;

/**
 * A reusable, account-level bundle of agent configuration (Settings →
 * Templates). Owned by the user; attached to agents via
 * `agents.agent_template_id`. While attached, the agent reads its per-surface
 * configuration (Hosted Chat / Widget / Bar) from the template's JSON
 * columns at runtime (see Agent::settings()).
 *
 * @property int $id
 * @property string $external_id
 * @property int $user_id
 * @property string $name
 * @property int|null $agent_prompt_id
 * @property bool $is_public
 * @property array<string, mixed>|null $hosted_chat_config
 * @property array<string, mixed>|null $widget_config
 * @property array<string, mixed>|null $bar_config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read AgentPrompt|null $agentPrompt
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Agent> $agents
 *
 * @method static \Database\Factories\AgentTemplateFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class AgentTemplate extends Model
{
    use HasExternalId, HasFactory;

    public static function cacheKey(int $id): string
    {
        return "agent_template:{$id}";
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AgentPrompt, $this>
     */
    public function agentPrompt(): BelongsTo
    {
        return $this->belongsTo(AgentPrompt::class);
    }

    /**
     * Agents currently attached to this template.
     *
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Project this template's config for the given surface onto the shape
     * HasSettings::settings() returns, so a templated agent's settings() call
     * can delegate here transparently. Returns an empty collection for groups
     * the template doesn't configure (e.g. `leads`, `business` — those stay
     * per-agent).
     *
     * @param  'hosted_chat'|'widget'|'bar'|string  $group
     */
    public function settingsFor(string $group): Collection
    {
        $config = $this->configFor($group);

        if ($config === null) {
            return collect();
        }

        $defaults = config('model-settings.defaults.'.Agent::class.'.'.$group, []);

        return collect($defaults)->merge($config);
    }

    /**
     * The allowed model identifiers for a surface. Hosted Chat may carry an
     * array (multi-model); Widget and Bar a single string promoted to a
     * one-element array.
     *
     * @return array<int, string>
     */
    public function modelsFor(string $surface): array
    {
        $config = $this->configFor($surface);

        if ($config === null) {
            return [];
        }

        if ($surface === 'hosted_chat') {
            return array_values($config['models'] ?? []);
        }

        return isset($config['model']) ? [$config['model']] : [];
    }

    /**
     * Default model for the surface — Hosted Chat's `default_model` (falls back
     * to the first allowed model) or Widget/Bar's single configured model.
     */
    public function defaultModelFor(string $surface): ?string
    {
        $config = $this->configFor($surface);

        if ($config === null) {
            return null;
        }

        if ($surface === 'hosted_chat') {
            return $config['default_model'] ?? ($config['models'][0] ?? null);
        }

        return $config['model'] ?? null;
    }

    /**
     * LLM tuning knobs (temperature, top_k, …) for the surface, or null when
     * the template doesn't configure them.
     *
     * @return array<string, float|int>|null
     */
    public function llmSettingsFor(string $surface): ?array
    {
        $config = $this->configFor($surface);

        return $config['llm_settings'] ?? null;
    }

    /**
     * Conversation starters for the surface — always an array (empty when
     * the template doesn't configure them).
     *
     * @return array<int, string>
     */
    public function startersFor(string $surface): array
    {
        $config = $this->configFor($surface);

        return array_values($config['starters'] ?? []);
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    protected function casts(): array
    {
        return [
            'hosted_chat_config' => 'array',
            'widget_config' => 'array',
            'bar_config' => 'array',
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function configFor(string $surface): ?array
    {
        return match ($surface) {
            'hosted_chat' => $this->hosted_chat_config,
            'widget' => $this->widget_config,
            'bar' => $this->bar_config,
            default => null,
        };
    }
}
