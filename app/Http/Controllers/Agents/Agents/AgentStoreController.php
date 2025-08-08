<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Http\Requests\Agents\CreateAgentRequest;
use App\Models\Agent;
use App\Support\Ingestion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class AgentStoreController
{
    public function __invoke(CreateAgentRequest $request, Ingestion $ingestion)
    {
        $user = $request->user();

        // TODO: check if user can create more agents

        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $user->id,
                'name' => $validatedData['name'],
                'sub_domain' => $validatedData['sub_domain'],
                'system_instructions' => $validatedData['system_instructions'],
                'is_public' => $request->input('is_public'),
                'type' => $validatedData['type'],
            ]);

            if ($agent->type === 'knowledge') {
                /** @var UploadedFile[] $files */
                $files = $validatedData['files'];

                foreach ($files as $file) {
                    $agentFile = $agent->addFile($file);
                    $agentFile->ingest();
                }
            }

            DB::commit();

            $route = $agent->type === 'knowledge' ? 'agents.knowledge' : 'agents.stats';

            return to_route($route, ['agent' => $agent->id])
                ->with('success', 'Agent created successfully. Files are being processed.');

        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return back()
                ->withErrors(['general' => 'Failed to create agent. Please try again.'])
                ->withInput();
        }
    }
}
