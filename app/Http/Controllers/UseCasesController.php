<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class UseCasesController
{
    public function __invoke(Request $request, string $case): View
    {
        $validCases = ['law', 'healthcare', 'finance', 'education', 'real-estate'];

        if (! in_array($case, $validCases)) {
            abort(404);
        }

        return view('landing.use-case', compact('case'));
    }
}
