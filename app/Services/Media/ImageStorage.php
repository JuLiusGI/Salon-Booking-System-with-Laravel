<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores uploaded images through the Laravel filesystem (MASTER_SPEC section 19).
 *
 * The original filename is never used. Laravel's store() generates a random hash
 * name and derives the extension from the file's detected type, so a file called
 * "invoice.php.jpg" cannot land on disk with an executable extension, and two
 * customers uploading "photo.jpg" cannot overwrite each other.
 *
 * Everything handled here is public-facing artwork (service photos, staff
 * portraits), so it lives on the public disk. Anything private must not use this
 * class without an authorization check in front of it.
 */
class ImageStorage
{
    public const DISK = 'public';

    /**
     * Accepted upload types. Kept in one place so the validation rules and the
     * storage layer cannot drift apart.
     *
     * @var list<string>
     */
    public const MIME_TYPES = ['jpeg', 'jpg', 'png', 'webp'];

    /** Maximum upload size in kilobytes. */
    public const MAX_KILOBYTES = 4096;

    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    /**
     * Replace an image, removing the previous file only once the new one is
     * safely written. If the write fails the old image is still there.
     */
    public function replace(?string $existingPath, UploadedFile $file, string $directory): string
    {
        $path = $this->store($file, $directory);

        $this->delete($existingPath);

        return $path;
    }

    /**
     * Remove a stored file. Missing files are not an error: the row may point at
     * something already deleted from disk, and the caller's intent is satisfied
     * either way.
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * A public URL for a stored path, or null when there is no image, so callers
     * can render a placeholder rather than a broken image.
     */
    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }
}
