<?php

namespace Domain\Form\Models;

use Database\Factories\FormDefinitionFactory;
use Domain\Agent\Models\Agent;
use Domain\Form\Enums\FormType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pgvector\Laravel\Vector;
use Support\Concerns\HasExternalId;

/**
 * A reusable form an agent can render inline in the chat. Agent-scoped (dies
 * with the agent). The `schema` is our normalized field array — the single
 * source of truth from which both the FormKit render schema and the server
 * validation rules are derived.
 *
 * @property int $id
 * @property string $external_id
 * @property int $agent_id
 * @property string $name
 * @property string|null $subtitle
 * @property string $slug
 * @property FormType $form_type
 * @property array<int, array<string, mixed>> $schema
 * @property array<string, string>|null $lead_field_map
 * @property string|null $success_message
 * @property string|null $agent_instructions
 * @property bool $is_active
 * @property Vector|null $embedding
 * @property string|null $embedding_bm25
 *
 * @method static \Database\Factories\FormDefinitionFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class FormDefinition extends Model
{
    /** @use HasFactory<FormDefinitionFactory> */
    use HasExternalId, HasFactory;

    /** The capability (builtin connector) an agent needs to show its forms in chat. */
    public const string CAPABILITY_SLUG = 'forms';

    protected function casts(): array
    {
        return [
            'form_type' => FormType::class,
            'schema' => 'array',
            'lead_field_map' => 'array',
            'is_active' => 'boolean',
            'embedding' => Vector::class,
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Delete some of this form's submissions, chosen by external id. An empty
     * list deletes nothing.
     *
     * @param  list<string>  $externalIds
     */
    public function deleteSubmissions(array $externalIds): void
    {
        if ($externalIds !== []) {
            $this->submissions()->whereIn('external_id', $externalIds)->delete();
        }
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forAgent(Builder $query, Agent $agent): void
    {
        $query->where('agent_id', $agent->id);
    }
}
