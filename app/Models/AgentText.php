<?php

namespace App\Models;

use App\Contracts\ChunkableContract;
use App\Traits\Chunkable;
use Illuminate\Database\Eloquent\Model;

class AgentText extends Model implements ChunkableContract
{
    use Chunkable;

    protected $guarded = ['id'];
}
