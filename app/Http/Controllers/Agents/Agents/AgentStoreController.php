<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Http\Requests\Agents\CreateAgentRequest;
use App\Models\Agent;
use App\Support\Ingestion;
use App\Support\PromptLoader;
use App\Support\Subdomain;
use Illuminate\Support\Facades\DB;
use Throwable;

class AgentStoreController
{
    public function __invoke(CreateAgentRequest $request, Ingestion $ingestion, Subdomain $subdomain)
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            $externalId = \App\Support\Agent::generateRandomExternalId();

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $user->id,
                'name' => $validatedData['name'],
                'sub_domain' => $validatedData['type'] === 'knowledge_base' ? $subdomain->generateRandomSubdomain() : null,
                'system_instructions' => PromptLoader::load('knowledge/system'),
                'is_public' => false,
                'type' => $validatedData['type'],
                'external_id' => $externalId,
            ]);

            $agent->setSetting(
                key: 'collect_leads',
                value: true,
                group: 'leads',
            );

            DB::commit();

            return to_route('agents.sources', ['agent' => $agent->id, 'new' => true])
                ->with('success', 'Agent created successfully.');

        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return back()
                ->withErrors(['general' => 'Failed to create agent. Please try again.'])
                ->withInput();
        }
    }
}
