<?php

namespace App\Http\Controllers;

use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PipelineStagesController extends Controller
{
    public function index(): Response
    {
        $stages = PipelineStage::ordered()->get();

        return Inertia::render('pipeline-stages/Index', [
            'stages' => $stages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pipeline_stages',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);

        // Get the next order number
        $maxOrder = PipelineStage::max('order') ?? 0;

        PipelineStage::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'color' => $request->color,
            'description' => $request->description,
            'order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Pipeline stage created successfully.');
    }

    public function update(Request $request, PipelineStage $stage): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pipeline_stages,slug,'.$stage->id,
            'color' => 'required|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);

        $stage->update($request->only(['name', 'slug', 'color', 'description']));

        return back()->with('success', 'Pipeline stage updated successfully.');
    }

    public function updateOrder(Request $request): JsonResponse
    {
        $request->validate([
            'stages' => 'required|array',
            'stages.*.id' => 'required|exists:pipeline_stages,id',
            'stages.*.order' => 'required|integer',
        ]);

        foreach ($request->stages as $stageData) {
            PipelineStage::where('id', $stageData['id'])
                ->update(['order' => $stageData['order']]);
        }

        return response()->json(['message' => 'Stage order updated successfully.']);
    }

    public function toggleActive(PipelineStage $stage): RedirectResponse
    {
        $stage->update(['is_active' => ! $stage->is_active]);

        $status = $stage->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Pipeline stage {$status} successfully.");
    }

    public function destroy(PipelineStage $stage): RedirectResponse
    {
        // Check if stage has leads
        if ($stage->leads()->count() > 0) {
            return back()->with('error', 'Cannot delete stage with existing leads. Move leads to another stage first.');
        }

        $stage->delete();

        return back()->with('success', 'Pipeline stage deleted successfully.');
    }
}
