<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Enums\ChatterSource;
use App\Identity\Chatter\Models\Chatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Exceptions\DomainValidationException;

/**
 * Creates a chatter (`source=api`) in a workspace and grants it access to one
 * or more of the workspace's agents through the `agent_chatter` pivot. Without
 * that grant the chatter could not sign in to any agent.
 *
 * An email may be used once per workspace (compared without case); the same
 * email in another workspace, or on a staff account, is a different person.
 */
class CreateChatterAction
{
    /**
     * @throws DomainValidationException when the workspace already has a chatter with this email
     */
    public function execute(CreateChatterData $data): Chatter
    {
        $email = $data->email !== null ? strtolower(trim($data->email)) : null;

        if ($email !== null && $data->workspace->chatters()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw new DomainValidationException('email', __('A chatter with this email already exists in your workspace.'));
        }

        return DB::transaction(function () use ($data, $email): Chatter {
            /** @var Chatter $chatter */
            $chatter = Chatter::query()->create([
                'workspace_id' => $data->workspace->id,
                'name' => $data->name,
                'email' => $email,
                'password' => $data->password !== null ? Hash::make($data->password) : null,
                'source' => ChatterSource::Api,
                'email_verified_at' => now(),
            ]);

            $chatter->agents()->attach($data->agentIds(), ['workspace_id' => $chatter->workspace_id]);

            return $chatter;
        });
    }
}
