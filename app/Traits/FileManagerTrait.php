<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

trait FileManagerTrait
{
    /**
     * upload method working for image
     * @param string $dir
     * @param string $format
     * @param $image
     * @return string
     */
    protected function upload(string $dir, string $format, $image = null): string
    {
        $storage = config('filesystems.disks.default') ?? 'public';
        Cache::forget("cache_all_files_for_public_storage");
        if (!is_null($image)) {
            if (!$this->checkFileExists($dir)['status']) {
                Storage::disk($storage)->makeDirectory($dir);
            }

            $isOriginalImage = in_array($image->getClientOriginalExtension(), ['gif', 'svg']);
            if ($isOriginalImage) {
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
                        $imageWebp = $imageInstance->encode($format);
                    } catch (\Intervention\Image\Exception\NotReadableException $encodeException) {
                        // If encoding fails with WebP error, force PNG
                        if (strtolower($format) === 'webp' || stripos($encodeException->getMessage(), 'webp') !== false) {
                            $format = 'png';
                            $imageInstance = Image::make($image); // Recreate instance
                            $imageWebp = $imageInstance->encode($format);
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
                            $imageWebp = $imageInstance->encode($format);
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
            $imageName = 'def.png';
        }

        cacheRemoveByType(type: 'file_manager');
        return $imageName;
    }

    /**
     * @param string $dir
     * @param string $format
     * @param $file
     * @return string
     */
    public function fileUpload(string $dir, string $format, $file = null): string
    {
        $storage = config('filesystems.disks.default') ?? 'public';
        Cache::forget("cache_all_files_for_public_storage");

        if (!is_null($file)) {
            $fileName = Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
            if (!$this->checkFileExists($dir)['status']) {
                Storage::disk($storage)->makeDirectory($dir);
            }
            if ($file) {
                Storage::disk($storage)->put($dir . $fileName, file_get_contents($file));
            }
        } else {
            $fileName = 'def.png';
        }

        return $fileName;
    }

    /**
     * @param string $dir
     * @param $oldImage
     * @param string $format
     * @param $image
     * @param string $fileType image/file
     * @return string
     */
    public function update(string $dir, $oldImage, string $format, $image, string $fileType = 'image'): string
    {
        if ($this->checkFileExists(filePath: $dir . $oldImage)['status']) {
            Storage::disk($this->checkFileExists(filePath: $dir . $oldImage)['disk'])->delete($dir . $oldImage);
        }
        return $fileType == 'file' ? $this->fileUpload($dir, $format, $image) : $this->upload($dir, $format, $image);
    }

    /**
     * @param string $filePath
     * @return array
     */
    protected function  delete(string $filePath): array
    {
        if ($this->checkFileExists(filePath: $filePath)['status']) {
            Storage::disk($this->checkFileExists(filePath: $filePath)['disk'])->delete($filePath);
        }
        cacheRemoveByType(type: 'file_manager');
        Cache::forget("cache_all_files_for_public_storage");
        return [
            'success' => 1,
            'message' => translate('Removed_successfully')
        ];
    }

    public function setStorageConnectionEnvironment(): void
    {
        $storageConnectionType = getWebConfig(name: 'storage_connection_type') ?? 'public';
        Config::set('filesystems.disks.default', $storageConnectionType);
        $storageConnectionS3Credential = getWebConfig(name: 'storage_connection_s3_credential');
        if ($storageConnectionType == 's3' && !empty($storageConnectionS3Credential)) {
            Config::set('filesystems.disks.' . $storageConnectionType, $storageConnectionS3Credential);
        }
    }

    private function checkFileExists(string $filePath): array
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
