<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\Changelog;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $changelogs = Changelog::with('items')
            ->orderBy('release_date', 'desc')
            ->get();

        $changeTypes = config('changelog.change_types', [
            'new_features' => [
                'label' => 'New Features',
                'color' => 'green',
                'icon' => '✓',
            ],
            'improvements' => [
                'label' => 'Improvements',
                'color' => 'blue',
                'icon' => '↑',
            ],
            'bug_fixes' => [
                'label' => 'Bug Fixes',
                'color' => 'yellow',
                'icon' => '🔧',
            ],
            'breaking_changes' => [
                'label' => 'Breaking Changes',
                'color' => 'red',
                'icon' => '⚠️',
            ],
            'core_features' => [
                'label' => 'Core Features',
                'color' => 'green',
                'icon' => '✓',
            ],
        ]);

        return view('changelog', compact('changelogs', 'changeTypes'));
    }
}
