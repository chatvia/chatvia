<?php

namespace App\Http\Controllers\Landing\Blog;

use App\Marketing\Enums\ChangelogChangeType;
use App\Marketing\Models\Changelog;
use Illuminate\Contracts\View\View;

/**
 * The public changelog page: every release, newest first, with its changes
 * grouped by type ({@see ChangelogChangeType}).
 */
class ChangelogController
{
    public function __invoke(): View
    {
        return view('changelog', [
            'changelogs' => Changelog::query()
                ->with('items')
                ->orderByDesc('release_date')
                ->get(),
        ]);
    }
}
