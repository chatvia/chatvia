<?php

declare(strict_types=1);

namespace App\Admin\Status;

/**
 * How a service on the Status page is doing: running, running with a catch
 * the admin should look at, down, or off (not set up, or not used here).
 */
enum ServiceState: string
{
    case Running = 'running';
    case Warning = 'warning';
    case Down = 'down';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('Running'),
            self::Warning => __('Needs a look'),
            self::Down => __('Down'),
            self::Off => __('Off'),
        };
    }

    /**
     * The label on an AI or other outside service: one that works is set
     * up, as nothing of it runs on this server.
     */
    public function outsideServiceLabel(): string
    {
        return $this === self::Running ? __('Set up') : $this->label();
    }

    /** The colour of its dot and badge, from the panel's palette. */
    public function tone(): string
    {
        return match ($this) {
            self::Running => 'success',
            self::Warning => 'warning',
            self::Down => 'danger',
            self::Off => 'gray',
        };
    }
}
