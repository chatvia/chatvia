<?php

namespace App\Http\Controllers\Api\V1\Function;

use App\Http\Requests\Api\V1\Function\StoreApiFunctionRequest;
use App\Http\Requests\Api\V1\Function\UpdateApiFunctionRequest;
use App\Http\Resources\Api\V1\Tools\FunctionData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Integration\Function\Actions\CreateAgentFunctionAction;
use Domain\Integration\Function\Actions\UpdateAgentFunctionAction;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Workspace webhook functions over the public API — the same Actions as the
 * dashboard's Settings → Functions page. Credentials are encrypted at rest
 * and never returned.
 */
#[Group(name: 'Functions', weight: 60)]
class FunctionController
{
    /**
     * List functions
     *
     * @return array{data: list<FunctionData>}
     */
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $owner = $user->workspace();

        return ['data' => $owner->functions()->latest()->get()
            ->map(fn (AgentFunction $f): FunctionData => FunctionData::fromModel($f))->all()];
    }

    /**
     * Create function
     *
     * @return array{data: FunctionData}
     */
    public function store(StoreApiFunctionRequest $request, CreateAgentFunctionAction $createFunction): array
    {
        /** @var User $user */
        $user = $request->user();

        return ['data' => FunctionData::fromModel($createFunction->execute($user, $request->toData()))];
    }

    /**
     * Get function
     *
     * @return array{data: FunctionData}
     */
    public function show(Request $request, AgentFunction $function): array
    {
        abort_if($request->user()->cannot('view', $function), 404);

        return ['data' => FunctionData::fromModel($function)];
    }

    /**
     * Update function
     *
     * @return array{data: FunctionData}
     */
    public function update(UpdateApiFunctionRequest $request, AgentFunction $function, UpdateAgentFunctionAction $updateFunction): array
    {
        abort_if($request->user()->cannot('update', $function), 404);

        return ['data' => FunctionData::fromModel($updateFunction->execute($function, $request->toData()))];
    }

    /**
     * Delete function
     */
    public function destroy(Request $request, AgentFunction $function): JsonResponse
    {
        abort_if($request->user()->cannot('delete', $function), 404);

        $function->delete();

        return response()->json(null, 204);
    }
}
