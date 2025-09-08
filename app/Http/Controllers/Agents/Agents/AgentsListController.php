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
                'agents.id',
                'agents.name',
                'agents.type',
                'agents.created_at',
            ])
            ->addSelect([
                'total_members' => DB::table('members')
                    ->join('agent_member', 'members.id', '=', 'agent_member.member_id')
                    ->whereColumn('agents.id', 'agent_member.agent_id')
                    ->selectRaw('count(*)'),
                'total_messages' => DB::table('chat_messages')
                    ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
                    ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
                    ->whereColumn('chat_sessions.agent_id', 'agents.id')
                    ->whereNull('chat_messages.deleted_at')
                    ->selectRaw('count(*)'),
                'unique_users' => DB::table('chat_sessions')
                    ->join('members', 'chat_sessions.member_id', '=', 'members.id')
                    ->whereColumn('chat_sessions.agent_id', 'agents.id')
                    ->whereNotNull('members.user_id')
                    ->selectRaw('count(distinct members.user_id)'),
            ])
            ->groupBy('agents.id', 'agents.name', 'agents.type', 'agents.created_at')
            ->latest()
            ->get();
    }
}
