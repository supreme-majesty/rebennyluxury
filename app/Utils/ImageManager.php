<?php

namespace App\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class ImageManager
{
    public static function upload(string $dir, string $format, $image, $file_type = 'image'): string
    {
        $storage = config('filesystems.disks.default') ?? 'public';
        if ($image != null) {
            if (!Storage::disk($storage)->exists($dir)) {
                Storage::disk($storage)->makeDirectory($dir);
            }

            $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $image->getClientOriginalExtension();
            Storage::disk($storage)->put($dir . $imageName, file_get_contents($image));

            if (in_array($image->getClientOriginalExtension(), ['gif', 'svg'])) {
                $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $image->getClientOriginalExtension();
                Storage::disk($storage)->put($dir . $imageName, file_get_contents($image));
            } else {
                // Check if WebP is supported when format is webp
                // Always check WebP support before attempting any encoding
                $webpSupported = (imagetypes() & IMG_WEBP) && function_exists('imagewebp');
                if (strtolower($format) === 'webp') {
                    if (!$webpSupported) {
                        $format = 'png';
                    }
                }

                try {
                    $imageInstance = Image::make($image);
                    // Double-check: if format is still webp but encoding fails, catch it
                    try {
                        $imageWebp = $imageInstance->encode($format, 85);
                    } catch (\Intervention\Image\Exception\NotReadableException $encodeException) {
                        // If encoding fails with WebP error, force PNG
                        if (strtolower($format) === 'webp' || stripos($encodeException->getMessage(), 'webp') !== false) {
                            $format = 'png';
                            $imageInstance = Image::make($image); // Recreate instance
                            $imageWebp = $imageInstance->encode($format, 85);
                        } else {
                            throw $encodeException;
                        }
                    }
                    $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
                    Storage::disk($storage)->put($dir . $imageName, $imageWebp);
                    $imageWebp->destroy();
                    $imageInstance->destroy();
                } catch (\Exception $e) {
                    // Final fallback: if all else fails, save as PNG or original format
                    if (stripos($e->getMessage(), 'webp') !== false || strtolower($format) === 'webp') {
                        $format = 'png';
                        try {
                            $imageInstance = Image::make($image);
                            $imageWebp = $imageInstance->encode($format, 85);
                            $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
                            Storage::disk($storage)->put($dir . $imageName, $imageWebp);
                            $imageWebp->destroy();
                            $imageInstance->destroy();
                        } catch (\Exception $fallbackException) {
                            // Last resort: save original image without processing
                            $originalExtension = $image->getClientOriginalExtension() ?: 'png';
                            $imageName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $originalExtension;
                            Storage::disk($storage)->put($dir . $imageName, file_get_contents($image));
                        }
                    } else {
                        throw $e;
                    }
                }
            }
        } else {
            $imageName = 'def.webp';
        }
        return $imageName;
    }

    public static function file_upload(string $dir, string $format, $file = null): string
    {
        $storage = config('filesystems.disks.default') ?? 'public';
        if ($file != null) {
            $fileName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
            if (!Storage::disk($storage)->exists($dir)) {
                Storage::disk($storage)->makeDirectory($dir);
            }
            Storage::disk($storage)->put($dir . $fileName, file_get_contents($file));
        } else {
            $fileName = 'def.png';
        }

        return $fileName;
    }

    public static function update(string $dir, $old_image, string $format, $image, $file_type = 'image'): string
    {
        if (self::checkFileExists(filePath: $dir.$old_image)['status']) {
            Storage::disk(self::checkFileExists(filePath: $dir . $old_image)['disk'])->delete($dir . $old_image);
        }

        return $file_type == 'file' ? ImageManager::file_upload($dir, $format, $image) : ImageManager::upload($dir, $format, $image);
    }

    public static function delete($full_path): array
    {
        if (self::checkFileExists(filePath: $full_path)['status']) {
            Storage::disk(self::checkFileExists(filePath: $full_path)['disk'])->delete($full_path);
        }
        return [
            'success' => 1,
            'message' => 'Removed successfully !'
        ];

    }
    public static function checkFileExists(string $filePath): array
    {
        if (Storage::disk('public')->exists($filePath)) {
            return [
                'status' => true,
                'disk' => 'public'
            ];
        } elseif (config('filesystems.disks.default') == 's3' && Storage::disk('s3')->exists($filePath)) {
            return [
                'status' => true,
                'disk' => 's3'
            ];
        } else {
            return [
                'status' => false,
                'disk' => config('filesystems.disks.default') ?? 'public'
            ];
        }
    }

}
