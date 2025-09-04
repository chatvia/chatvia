<?php

namespace App\Http\Controllers\Agents\Leads;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentLeadsController extends Controller
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        $query = Lead::where('agent_id', $agent->id)
            ->with(['agent', 'assignedTo'])
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->status, fn ($q) => $q->byStatus($request->status))
            ->when($request->priority, fn ($q) => $q->byPriority($request->priority))
            ->when($request->assigned_to, fn ($q) => $q->assignedTo(User::find($request->assigned_to)))
            ->when($request->pipeline_stage, fn ($q) => $q->byPipelineStage($request->pipeline_stage))
            ->when($request->tags, fn ($q) => $q->withTags(explode(',', $request->tags)))
            ->latest();

        // Get the leads and add computed attributes
        $leads = $query->get()->map(function ($lead) {
            return array_merge($lead->toArray(), [
                'display_name' => $lead->display_name,
                'initials' => $lead->initials,
                'priority_color' => $lead->priority_color,
                'priority_label' => $lead->priority_label,
                'assigned_to_user' => $lead->assignedTo,
            ]);
        })->groupBy('pipeline_stage');

        // Get pipeline stages
        $pipelineStages = PipelineStage::active()->ordered()->get();

        // Get team members for assignment
        $teamMembers = User::select('id', 'name', 'email')->get();

        // Get analytics data for this agent
        $analytics = [
            'total_leads' => Lead::where('agent_id', $agent->id)->count(),
            'new_leads_today' => Lead::where('agent_id', $agent->id)->whereDate('created_at', today())->count(),
            'conversion_rate' => $this->getConversionRate($agent),
            'avg_response_time' => $this->getAverageResponseTime($agent),
            'leads_by_source' => Lead::where('agent_id', $agent->id)
                ->selectRaw('source, count(*) as count')
                ->whereNotNull('source')
                ->groupBy('source')
                ->pluck('count', 'source'),
            'leads_by_priority' => Lead::where('agent_id', $agent->id)
                ->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority'),
        ];

        return Inertia::render('agents/Leads', [
            'agent' => $agent,
            'leads' => $leads,
            'pipelineStages' => $pipelineStages->pluck('name', 'slug'),
            'pipelineStagesData' => $pipelineStages,
            'teamMembers' => $teamMembers,
            'analytics' => $analytics,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'priority' => $request->priority,
                'assigned_to' => $request->assigned_to,
                'pipeline_stage' => $request->pipeline_stage,
                'tags' => $request->tags,
            ],
        ]);
    }

    public function updatePipelineStage(Request $request, Agent $agent, Lead $lead): RedirectResponse
    {
        // Ensure the lead belongs to this agent
        if ($lead->agent_id !== $agent->id) {
            abort(403, 'This lead does not belong to this agent.');
        }

        $request->validate([
            'pipeline_stage' => 'required|exists:pipeline_stages,slug',
        ]);

        $lead->updatePipelineStage($request->pipeline_stage);

        return back()->with('success', 'Pipeline stage updated successfully.');
    }

    public function assign(Request $request, Agent $agent, Lead $lead): RedirectResponse
    {
        // Ensure the lead belongs to this agent
        if ($lead->agent_id !== $agent->id) {
            abort(403, 'This lead does not belong to this agent.');
        }

        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $user = User::find($request->assigned_to);
        $lead->assignTo($user);

        return back()->with('success', "Lead assigned to {$user->name} successfully.");
    }

    public function addNote(Request $request, Agent $agent, Lead $lead): RedirectResponse
    {
        // Ensure the lead belongs to this agent
        if ($lead->agent_id !== $agent->id) {
            abort(403, 'This lead does not belong to this agent.');
        }

        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $lead->addNote($request->note);

        return back()->with('success', 'Note added successfully.');
    }

    public function addTag(Request $request, Agent $agent, Lead $lead): RedirectResponse
    {
        // Ensure the lead belongs to this agent
        if ($lead->agent_id !== $agent->id) {
            abort(403, 'This lead does not belong to this agent.');
        }

        $request->validate([
            'tag' => 'required|string|max:50',
        ]);

        $lead->addTag($request->tag);

        return back()->with('success', 'Tag added successfully.');
    }

    private function getConversionRate(Agent $agent): float
    {
        $totalLeads = Lead::where('agent_id', $agent->id)->count();
        $convertedLeads = Lead::where('agent_id', $agent->id)->where('status', 'converted')->count();

        return $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 2) : 0;
    }

    private function getAverageResponseTime(Agent $agent): ?float
    {
        // Calculate average time between lead creation and first contact for this agent
        $leads = Lead::where('agent_id', $agent->id)->whereNotNull('contacted_at')->get();

        if ($leads->isEmpty()) {
            return null;
        }

        $totalHours = $leads->sum(function ($lead) {
            return $lead->created_at->diffInHours($lead->contacted_at);
        });

        return round($totalHours / $leads->count(), 1);
    }
}
