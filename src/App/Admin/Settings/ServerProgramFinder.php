<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Support\Facades\Process;

/**
 * Finds the programs of Settings → Platform → Programs on this server, for
 * the admin panel: the file a path leads to ({@see find()}, file checks
 * only, cheap enough for every render), and the full path of a program when
 * the admin asks for it ({@see detect()}, which runs `which`).
 *
 * It looks with the PATH of the web server. The queue worker may have
 * another PATH, which is why a full path is the safe choice.
 */
final class ServerProgramFinder
{
    /** Folders programs are often in, but which the PATH of a service may miss (Homebrew's on a Mac). */
    private const array USUAL_FOLDERS = [
        '/opt/homebrew/bin',
        '/usr/local/bin',
        '/usr/bin',
        '/bin',
        '/opt/local/bin',
        '/snap/bin',
    ];

    /**
     * @param  list<string>|null  $usualFolders  The folders {@see detect()} adds to the PATH; null for the usual ones.
     */
    public function __construct(
        private readonly ?array $usualFolders = null,
    ) {}

    /**
     * The file the app runs for a path: the path itself when it is a full
     * path, otherwise the first match on the PATH. Null when there is none.
     */
    public function find(string $path): ?string
    {
        if (str_starts_with($path, '/')) {
            return $this->isProgram($path) ? $path : null;
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $folder) {
            if ($folder !== '' && $this->isProgram("{$folder}/{$path}")) {
                return "{$folder}/{$path}";
            }
        }

        return null;
    }

    /**
     * The full path `which` gives for the program, looking on the PATH and
     * then in the usual folders. Null when it finds none, or gives a path
     * the program's field would refuse.
     */
    public function detect(ServerProgram $program): ?string
    {
        $folders = [...explode(PATH_SEPARATOR, (string) getenv('PATH')), ...($this->usualFolders ?? self::USUAL_FOLDERS)];

        $result = Process::env(['PATH' => implode(PATH_SEPARATOR, array_unique(array_filter($folders)))])
            ->timeout(10)
            ->run(['which', $program->value]);

        $path = trim((string) strtok($result->output(), "\n"));

        return $result->successful() && $program->acceptsPath($path) && $path !== $program->value && $this->isProgram($path)
            ? $path
            : null;
    }

    private function isProgram(string $path): bool
    {
        return @is_file($path) && @is_executable($path);
    }
}
