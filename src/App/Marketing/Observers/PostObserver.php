<?php

namespace App\Marketing\Observers;

use App\Marketing\Models\Post;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Keeps a blog post's cover image small and tidy: a newly set image is cropped
 * to 768×432 and re-encoded as WebP, and the image file is deleted with the
 * post.
 */
class PostObserver
{
    /**
     * Handle the Post "saving" event.
     */
    public function saving(Post $post): void
    {
        if ($post->isDirty('image_path') && $post->image_path) {
            $this->optimizeImage($post);
        }
    }

    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void
    {
        if ($post->image_path) {
            Storage::disk('private')->delete($post->image_path);
        }
    }

    /**
     * Optimize and resize the uploaded image.
     */
    protected function optimizeImage(Post $post): void
    {
        $disk = Storage::disk('private');
        $imagePath = $post->image_path;

        if (! $disk->exists($imagePath)) {
            return;
        }

        $imageContent = $disk->get($imagePath);

        $image = Image::decodeBinary($imageContent)
            ->cover(768, 432)
            ->encode(new WebpEncoder(quality: 85));

        $pathInfo = pathinfo($imagePath);
        $newPath = $pathInfo['dirname'].'/'.$pathInfo['filename'].'.webp';

        $disk->put($newPath, (string) $image, [
            'visibility' => 'public',
            'CacheControl' => 'max-age=31536000',
        ]);

        if ($newPath !== $imagePath) {
            $disk->delete($imagePath);
            $post->image_path = $newPath;
        }
    }
}
