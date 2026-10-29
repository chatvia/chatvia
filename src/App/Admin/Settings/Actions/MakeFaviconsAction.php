<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\CustomLogo;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Makes the favicons of the install's own icon (Settings → Platform): the PNG
 * sizes of {@see CustomLogo::FAVICONS} and a favicon.ico, next to it on the
 * `assets` disk, in the folder {@see CustomLogo::faviconDirectory()} names.
 * The panel runs it when an icon is saved.
 */
final readonly class MakeFaviconsAction
{
    public function execute(string $logoPath): void
    {
        $disk = Storage::disk('assets');
        $source = $disk->get($logoPath);
        $directory = CustomLogo::faviconDirectory($logoPath);

        foreach (CustomLogo::FAVICONS as $file => $size) {
            $disk->put("{$directory}/{$file}", $this->png($source, $size));
        }

        $pngs = collect(CustomLogo::ICO_SIZES)
            ->mapWithKeys(fn (int $size): array => [$size => $this->png($source, $size)])
            ->all();

        $disk->put("{$directory}/favicon.ico", $this->ico($pngs));
    }

    /** The icon as a square PNG of $size pixels. */
    private function png(string $source, int $size): string
    {
        return (string) Image::decodeBinary($source)
            ->cover($size, $size)
            ->encode(new PngEncoder);
    }

    /**
     * An ICO file that holds the PNGs as they are, which every browser reads:
     * a 6-byte header, a 16-byte entry per image, then the images.
     *
     * @param  array<int, string>  $pngs  Each PNG by the side of its square.
     */
    private function ico(array $pngs): string
    {
        $header = pack('vvv', 0, 1, count($pngs));
        $offset = 6 + 16 * count($pngs);
        $entries = '';

        foreach ($pngs as $size => $png) {
            // A side of 256 is written as 0; the sizes are bytes, not characters.
            $entries .= pack('CCCCvvVV', $size % 256, $size % 256, 0, 0, 1, 32, strlen($png), $offset);
            $offset += strlen($png);
        }

        return $header.$entries.collect($pngs)->implode('');
    }
}
