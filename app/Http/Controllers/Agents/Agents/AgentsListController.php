<?php

namespace App\Http\Controllers\Agents\Agents;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentsListController
{
    public function __invoke(Request $request)
    {
        return $request
            ->user()
            ->agents()
            ->select([
                'id',
                'name',
                'type',
                'created_at',
            ])
            ->withCount('members as total_members')
            ->addSelect([
                'total_messages' => DB::table('chat_messages')
                    ->join('chat_conversations', 'chat_messages.conversation_id', '=', 'chat_conversations.id')
                    ->join('chat_sessions', 'chat_conversations.session_id', '=', 'chat_sessions.id')
                    ->whereColumn('chat_sessions.agent_id', 'agents.id')
                    ->whereNull('chat_messages.deleted_at')
                    ->selectRaw('count(*)'),
                'unique_users' => DB::table('chat_sessions')
                    ->whereColumn('agent_id', 'agents.id')
                    ->whereNotNull('user_id')
                    ->selectRaw('count(distinct user_id)'),
            ])
            ->latest()
            ->get();
    }
}
