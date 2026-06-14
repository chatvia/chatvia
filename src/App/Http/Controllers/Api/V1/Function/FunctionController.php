<?php

namespace App\Http\Controllers\Api\V1\Function;

use App\Http\Requests\Api\V1\Function\StoreApiFunctionRequest;
use App\Http\Requests\Api\V1\Function\UpdateApiFunctionRequest;
use App\Http\Resources\Api\V1\Tools\FunctionData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Integration\Function\Actions\CreateApiFunctionAction;
use Domain\Integration\Function\Actions\UpdateApiFunctionAction;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account-level webhook-backed functions. Auth credentials are encrypted at
 * rest and never returned.
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
    public function store(StoreApiFunctionRequest $request, CreateApiFunctionAction $createFunction): array
    {
        /** @var User $user */
        $user = $request->user();
        $owner = $user->workspace();

        return ['data' => FunctionData::fromModel($createFunction->execute($owner, $request->toData()))];
    }

    /**
     * Get function
     *
     * @return array{data: FunctionData}
     */
    public function show(Request $request, AgentFunction $function): array
    {
        $this->assertOwnership($request, $function);

        return ['data' => FunctionData::fromModel($function)];
    }

    /**
     * Update function
     *
     * @return array{data: FunctionData}
     */
    public function update(UpdateApiFunctionRequest $request, AgentFunction $function, UpdateApiFunctionAction $updateFunction): array
    {
        $this->assertOwnership($request, $function);

        return ['data' => FunctionData::fromModel($updateFunction->execute($function, $request->toData()))];
    }

    /**
     * Delete function
     */
    public function destroy(Request $request, AgentFunction $function): JsonResponse
    {
        $this->assertOwnership($request, $function);

        $function->delete();

        return response()->json(null, 204);
    }

    private function assertOwnership(Request $request, AgentFunction $function): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($function->user_id !== $user->workspace()->id, 404);
    }
}
