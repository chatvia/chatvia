<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $agent_id
 * @property int $llm_id
 * @property bool $is_default
 * @property int $sort_order
 * @property bool $is_active
 * @property float $temperature
 * @property float $top_p
 * @property float $top_k
 * @property float $frequency_penalty
 * @property float $presence_penalty
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereFrequencyPenalty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereLlmId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm wherePresencePenalty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereTemperature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereTopK($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentLlm whereTopP($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'agent_llm')]
class AgentLlm extends Model {}
