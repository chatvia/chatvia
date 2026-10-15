<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admin\Pages\Tools;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Horizon\Horizon;

/**
 * A tool that watches the install from outside the admin panel, with a card
 * on the Tools page ({@see Tools}): what it is for, and where it opens. A
 * card links to a tool only when this install has it and the tool's own
 * check lets the signed-in admin in, so it never leads to an error page.
 */
enum StaffTool: string
{
    case Horizon = 'horizon';
    case Pulse = 'pulse';

    public function label(): string
    {
        return match ($this) {
            self::Horizon => __('Laravel Horizon'),
            self::Pulse => __('Laravel Pulse'),
        };
    }

    /** What the tool looks after, in a few words. */
    public function tagline(): string
    {
        return match ($this) {
            self::Horizon => __('Queues and workers'),
            self::Pulse => __('App health and performance'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Horizon => __('See the background jobs: what waits in the queue, what runs now and what failed. Retry a failed job, and check that the workers keep up.'),
            self::Pulse => __('See how the app performs: slow pages, slow database queries and jobs, errors, and the people and jobs that use the server most.'),
        };
    }

    /** The URL of the logo the tool shows on its own dashboard. */
    public function logo(): string
    {
        return asset("images/logos/tools/{$this->value}.svg");
    }

    public function url(): string
    {
        return route($this->routeName());
    }

    /** Why the card cannot open the tool, in a few words; null when it can. */
    public function unavailableReason(): ?string
    {
        return match (true) {
            ! Route::has($this->routeName()) => __('Not installed'),
            ! $this->canOpen() => __('You cannot open it'),
            default => null,
        };
    }

    /**
     * The tool's own check, as it runs when the tool opens: Horizon also
     * lets everyone in on a local install.
     */
    private function canOpen(): bool
    {
        return match ($this) {
            self::Horizon => Horizon::check(request()),
            self::Pulse => Gate::allows('viewPulse'),
        };
    }

    /** The route of the tool's dashboard, which only an installed tool has. */
    private function routeName(): string
    {
        return match ($this) {
            self::Horizon => 'horizon.index',
            self::Pulse => 'pulse',
        };
    }
}
