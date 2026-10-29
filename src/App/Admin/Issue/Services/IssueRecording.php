<?php

declare(strict_types=1);

namespace App\Admin\Issue\Services;

use Illuminate\Support\Facades\Context;

/**
 * Whether outside calls made now may become issues. The Settings and
 * Status checks run muted: they test a key the admin may still be typing,
 * so a refusal there says nothing about the install.
 */
final class IssueRecording
{
    private const string MUTED = 'issues_muted';

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function muted(callable $callback): mixed
    {
        return Context::scope($callback, hidden: [self::MUTED => true]);
    }

    public static function isOn(): bool
    {
        return config('chatvia.issues.enabled') === true && Context::getHidden(self::MUTED) !== true;
    }
}
