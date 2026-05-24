<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Model;

class AgentLlm extends Model
{
    protected $table = 'agent_llm';

    protected $guarded = ['id'];
}
