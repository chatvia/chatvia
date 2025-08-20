<?php

namespace App\Http\Controllers\Agents\Security;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentSecurityUpdateController extends Controller
{
    public function __invoke(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'is_public' => 'required|boolean',
            'enable_widget' => 'required|boolean',
            'enable_iframe_embedding' => 'required|boolean',
        ]);

        $agent->update($validated);

        return back()->with('success', 'Security settings updated successfully');
    }
}
