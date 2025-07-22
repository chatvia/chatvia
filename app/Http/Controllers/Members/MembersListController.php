<?php

namespace App\Http\Controllers\Members;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

class MembersListController
{
    public function __invoke(Request $request)
    {
        return $request
            ->user()
            ->members()
            ->select([
                'members.id',
                'members.name',
                'members.email',
                'members.email_verified_at',
                'members.created_at',
            ])
            ->with('agents', function (BelongsToMany $relation) {
                $relation->select([
                    'agents.id',
                    'agents.name',
                    'agents.sub_domain',
                ]);
            })
            ->get();
    }
}
