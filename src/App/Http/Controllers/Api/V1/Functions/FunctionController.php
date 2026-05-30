<?php

namespace App\Http\Controllers\Api\V1\Functions;

use App\Auth\Models\User;
use App\Http\Requests\Api\V1\Functions\StoreApiFunctionRequest;
use App\Http\Requests\Api\V1\Functions\UpdateApiFunctionRequest;
use App\Http\Resources\Api\V1\FunctionData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Actions\CreateApiFunctionAction;
use Domain\Agents\Actions\UpdateApiFunctionAction;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account-level webhook-backed functions. Auth credentials are encrypted at
 * rest and never returned.
 */
#[Group(name: 'Functions', weight: 60)]
class FunctionController
{
    /** @return array{data: list<FunctionData>} */
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return ['data' => $user->functions()->latest()->get()
            ->map(fn (AgentFunction $f): FunctionData => FunctionData::fromModel($f))->all()];
    }

    /** @return array{data: FunctionData} */
    public function store(StoreApiFunctionRequest $request, CreateApiFunctionAction $createFunction): array
    {
        /** @var User $user */
        $user = $request->user();

        return ['data' => FunctionData::fromModel($createFunction->execute($user, $request->toData()))];
    }

    /** @return array{data: FunctionData} */
    public function show(Request $request, AgentFunction $function): array
    {
        $this->assertOwnership($request, $function);

        return ['data' => FunctionData::fromModel($function)];
    }

    /** @return array{data: FunctionData} */
    public function update(UpdateApiFunctionRequest $request, AgentFunction $function, UpdateApiFunctionAction $updateFunction): array
    {
        $this->assertOwnership($request, $function);

        return ['data' => FunctionData::fromModel($updateFunction->execute($function, $request->toData()))];
    }

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
        abort_if($function->user_id !== $user->id, 404);
    }
}
