<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadsController extends Controller
{
    public function index(): Response
    {
        $leads = Lead::with('agent')
            ->latest()
            ->get()
            ->groupBy('status');

        return Inertia::render('leads/Index', [
            'leads' => $leads,
            'statusColumns' => [
                'new' => 'New',
                'contacted' => 'Contacted',
                'qualified' => 'Qualified',
                'converted' => 'Converted',
            ],
        ]);
    }

    public function updateStatus(Request $request, Lead $lead)
    {
        $request->validate([
            'status' => 'required|in:new,contacted,qualified,converted',
        ]);

        $lead->update([
            'status' => $request->status,
            $request->status.'_at' => now(),
        ]);

        return back();
    }
}
