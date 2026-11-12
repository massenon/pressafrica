<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploads under random names (never the client's file name) on the public disk.
 * JPEG and PNG are re-encoded to WebP and capped in width (EX-24, "Auto WebP"); re-encoding also drops EXIF
 * (GPS position of the photographer). GIF is kept as is so animations survive; PDFs are stored untouched.
 */
class MediaStore
{
    public const DISK = 'public';

    private const MAX_WIDTH = 2400;

    private const WEBP_QUALITY = 82;

    /** @return array{path: string, mime: string, size: int, width: ?int, height: ?int} */
    public static function store(UploadedFile $file): array
    {
        $dir = 'media/'.now()->format('Y/m');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        if (in_array($mime, ['image/jpeg', 'image/png'], true) && function_exists('imagewebp')) {
            $img = $mime === 'image/png' ? imagecreatefrompng($file->getRealPath()) : imagecreatefromjpeg($file->getRealPath());
            if (imagesx($img) > self::MAX_WIDTH) {
                $img = imagescale($img, self::MAX_WIDTH);
            }
            imagepalettetotruecolor($img);
            imagesavealpha($img, true);
            ob_start();
            imagewebp($img, null, self::WEBP_QUALITY);
            $bytes = ob_get_clean();
            [$w, $h] = [imagesx($img), imagesy($img)];
            imagedestroy($img);

            $path = $dir.'/'.Str::random(32).'.webp';
            Storage::disk(self::DISK)->put($path, $bytes);

            return ['path' => $path, 'mime' => 'image/webp', 'size' => strlen($bytes), 'width' => $w, 'height' => $h];
        }

        $path = $file->storeAs($dir, Str::random(32).'.'.$file->guessExtension(), self::DISK);
        $size = @getimagesize($file->getRealPath()) ?: [null, null];

        return ['path' => $path, 'mime' => $mime, 'size' => $file->getSize(), 'width' => $size[0], 'height' => $size[1]];
    }

    public static function url(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }

    public static function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }
}
