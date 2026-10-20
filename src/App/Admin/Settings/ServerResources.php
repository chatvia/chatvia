<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * What this server gives the queue workers: its memory, the container's own
 * memory limit when one is set, and its processor count. Detection reads the
 * kernel's files (cgroup v2, cgroup v1, /proc), so it sees the same numbers
 * in a container and on a bare server; macOS asks sysctl (development only).
 */
final readonly class ServerResources
{
    private const int GIB = 1024 ** 3;

    public function __construct(
        public int $memoryBytes,
        public ?int $memoryLimitBytes,
        public int $cpus,
    ) {}

    public static function detect(): self
    {
        $memory = self::hostMemory();
        $limit = self::cgroupMemoryLimit();

        // "No limit" is often written as a number far above the machine.
        if ($limit !== null && $limit >= $memory) {
            $limit = null;
        }

        return new self($memory, $limit, self::cpuCount());
    }

    private static function hostMemory(): int
    {
        $meminfo = self::read('/proc/meminfo');

        if (is_string($meminfo) && preg_match('/^MemTotal:\s+(\d+) kB/m', $meminfo, $matches) === 1) {
            return (int) $matches[1] * 1024;
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $bytes = (int) trim(Process::run(['sysctl', '-n', 'hw.memsize'])->output());

            if ($bytes > 0) {
                return $bytes;
            }
        }

        // Unknown machine: assume the small server the defaults fit.
        return 4 * self::GIB;
    }

    private static function cgroupMemoryLimit(): ?int
    {
        foreach (['/sys/fs/cgroup/memory.max', '/sys/fs/cgroup/memory/memory.limit_in_bytes'] as $file) {
            $value = self::read($file);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value !== '' && $value !== 'max') {
                return (int) $value;
            }
        }

        return null;
    }

    private static function cpuCount(): int
    {
        // A container's CPU quota, "200000 100000" = 2 processors; "max" is none.
        $quota = self::read('/sys/fs/cgroup/cpu.max');

        if (is_string($quota) && preg_match('/^(\d+) (\d+)/', trim($quota), $matches) === 1) {
            return max(1, (int) ceil((int) $matches[1] / (int) $matches[2]));
        }

        $cpuinfo = self::read('/proc/cpuinfo');

        if (is_string($cpuinfo) && ($count = preg_match_all('/^processor\s*:/m', $cpuinfo)) > 0) {
            return $count;
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $count = (int) trim(Process::run(['sysctl', '-n', 'hw.ncpu'])->output());

            if ($count > 0) {
                return $count;
            }
        }

        return 2;
    }

    /** The file's contents, or null when it is missing or cannot be read. */
    private static function read(string $file): ?string
    {
        return rescue(fn (): string => File::get($file), null, report: false);
    }
}
