<?php

use App\Models\Agent;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('agents.{id}.crawler', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});
