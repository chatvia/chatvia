<?php

namespace Support\Browsershot;

use Spatie\Browsershot\Browsershot;

/**
 * Centralises Browsershot binary-path setup so PDF export and the website
 * crawler don't drift on the include-path/node/npm/chrome combo. Reads paths
 * from config('services.bin.*').
 */
class BrowsershotConfigurator
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
