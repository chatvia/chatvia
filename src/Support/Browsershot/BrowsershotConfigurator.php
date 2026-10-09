<?php

namespace Support\Browsershot;

use Spatie\Browsershot\Browsershot;

/**
 * Points Browsershot at the server's node, npm and Chrome binaries
 * (`config('services.bin.*')`), so every PDF render — the chat export and the
 * DPA download — uses the same setup. A path that is not configured keeps
 * Browsershot's own default.
 */
final readonly class BrowsershotConfigurator
{
    public function configure(Browsershot $browsershot): Browsershot
    {
        if ($includePath = config('services.bin.nvm_include_path')) {
            $browsershot->setIncludePath($includePath);
        }

        if ($node = config('services.bin.node')) {
            $browsershot->setNodeBinary($node);
        }

        if ($npm = config('services.bin.npm')) {
            $browsershot->setNpmBinary($npm);
        }

        if ($chrome = config('services.bin.chrome')) {
            $browsershot->setChromePath($chrome);
        }

        return $browsershot;
    }
}
