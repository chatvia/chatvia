<?php

use App\Models\Agent;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('agents.{id}.crawler', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.crawler.progress', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.files.progress', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.texts.progress', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.ingestion.text', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.web.ingestion.url', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});
