<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Str;
use App\Jobs\AddWatermarkJob;
use App\Jobs\OptimizeImageJob;
use App\Services\HelperService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class FileService
{
    /**
     * Compress and upload an image (optional watermark)
     *
     * @param $requestFile
     * @param string $folder
     * @param bool $addWaterMark
     * @return string|false
     */
    public static function compressAndUpload($requestFile, string $folder, bool $addWaterMark = false)
    {
        $filenameWithoutExt = pathinfo($requestFile->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($requestFile->getClientOriginalExtension());
        $fileName = time() . '-' . Str::slug($filenameWithoutExt) . '.' . $extension;
        $disk = config('filesystems.default');
        $path = $folder . '' . $fileName;

        try {
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                // Compress and save image
                $image = Image::make($requestFile)->encode($extension, 80);
                Storage::disk($disk)->put($path, (string)$image);

                // Get absolute path safely
                $absolutePath = self::getAbsolutePath($disk, $path);
                if (!$absolutePath) {
                    Log::warning('Cannot get absolute path for image', ['disk' => $disk, 'path' => $path]);
                    return $fileName;
                }

                // Optimize image (queue for background processing)
                $fileSize = filesize($absolutePath);
                $maxSizeForOptimization = 5 * 1024 * 1024; // 5MB

                if ($fileSize < $maxSizeForOptimization) {
                    OptimizeImageJob::dispatch($absolutePath);
                } else {
                    Log::info('Skipping optimization for large image', [
                        'file' => $absolutePath,
                        'size' => $fileSize
                    ]);
                }

                // Queue watermark if enabled
                if ($addWaterMark && HelperService::getWatermarkConfigStatus()) {
                    AddWatermarkJob::dispatch($absolutePath, $extension)->delay(now()->addSeconds(5)); // small safety delay
                } elseif ($addWaterMark) {
                    Log::info('Watermark skipped: watermark is disabled');
                }

                return $fileName;
            }

            // Non-image files
            $requestFile->storeAs($folder, $fileName, $disk);
            return $fileName;

        } catch (Exception $e) {
            Log::error('FileService::compressAndUpload error: ' . $e->getMessage(), ['file' => $path]);
            return false;
        }
    }

    /**
     * Simple upload (non-compressed)
     *
     * @param $requestFile
     * @param string $folder
     * @return string
     */
    public static function upload($requestFile, string $folder): string
    {
        $fileName = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();
        $requestFile->storeAs($folder, $fileName, 'public');
        return $folder . '/' . $fileName;
    }

    /**
     * Replace a file with new upload
     */
    public static function replace($requestFile, string $folder, $deleteRawOriginalImage)
    {
        self::delete($folder, $deleteRawOriginalImage);
        return self::upload($requestFile, $folder);
    }

    /**
     * Compress and replace an existing image
     */
    public static function compressAndReplace($requestFile, string $folder, $deleteRawOriginalImage, bool $addWaterMark = false)
    {
        if (!empty($deleteRawOriginalImage)) {
            self::delete($folder, $deleteRawOriginalImage);
        }
        return self::compressAndUpload($requestFile, $folder, $addWaterMark);
    }

    /**
     * File Exists
     */
    public static function fileExists(string $filePath): bool
    {
        $disk = config('filesystems.default');
        $absolutePath = self::getAbsolutePath($disk, $filePath);
        return $absolutePath ? true : false;
    }

    /**
     * Get absolute local path for a file on any disk
     */
    public static function getAbsolutePath(string $disk, string $filePath): ?string
    {
        try {
            $diskInstance = Storage::disk($disk);

            if (method_exists($diskInstance, 'path')) {
                $absolutePath = $diskInstance->path($filePath);
                return file_exists($absolutePath) ? $absolutePath : null;
            }
        } catch (Exception $e) {
            Log::warning('FileService::getAbsolutePath failed', ['disk' => $disk, 'file' => $filePath, 'error' => $e->getMessage()]);
        }

        return null; // Non-local disk
    }

    /**
     * Get URL for a file
     */
    public static function getFileUrl(string $imagePath): ?string
    {
        if(self::fileExists($imagePath)){
            return Storage::url($imagePath);
        }
        return null;
    }

    /**
     * Delete a file if exists
     */
    public static function delete(string $folder, $image): bool
    {
        if (!empty($image) && Storage::disk(config('filesystems.default'))->exists($folder . '/' . $image)) {
            return Storage::disk(config('filesystems.default'))->delete($folder . '/' . $image);
        }
        return true;
    }
    public static function generateBlurData(string $path, int $width = 20, int $height = 20, int $blur = 10, int $quality = 50): string
    {
        $img = Image::make($path)
            ->resize($width, $height)
            ->blur($blur);

        // Detect original image extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $extension = 'jpg'; // fallback
        }

        // encode to original format
        $img->encode($extension, $quality);

        return base64_encode($img);
    }

    public static function getBlurDataUri(string $path, int $width = 20, int $height = 20, int $blur = 10, int $quality = 50): string
    {
        $filesystem = config('filesystems.default');
        $storagePath = self::getAbsolutePath($filesystem, ltrim($path, '/'));
        if (!$storagePath) {
            Log::info('File not found: ' . $path);
            return false;
        }

        $base64 = self::generateBlurData($storagePath, $width, $height, $blur, $quality);

        // Detect mime type
        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
        $mime = match($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . $base64;
    }


     /**
     * Generate or return cached blur image URL
     *
     * @param string $imagePath Full storage path of original image
     * @param string $cacheKey Unique cache key (e.g., 'module_blur_{module}_{id}')
     * @param string|null $filename Optional filename to save
     * @return string|null Public URL
     */
    public static function getCachedBlurImageUrl(string $imagePath, string $cacheKey, string $filename = null): ?string
    {
        try {
            $filename = $filename ?? 'blur_' . basename($imagePath);
            $storagePath = "blur_cache/{$filename}";
            $filesystem = config('filesystems.default');

            if (!Cache::has($cacheKey) || !Storage::disk($filesystem)->exists($storagePath)) {

                $originalImagePath = self::getAbsolutePath($filesystem, $imagePath);
                if (!file_exists($originalImagePath)) return null;

                // Generate tiny blurred image
                $blurData = self::getBlurDataUri($imagePath, 20, 20, 10, 50);

                [$type, $data] = explode(',', $blurData);
                $imageBinary = base64_decode($data);

                Storage::disk($filesystem)->put($storagePath, $imageBinary);

                Cache::forever($cacheKey, $storagePath);
            } else {
                $storagePath = Cache::get($cacheKey);
            }

            return Storage::disk($filesystem)->url($storagePath);

        } catch (Exception $e) {
            Log::error("FileService getCachedBlurImageUrl error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Clear cached blur image
     *
     * @param string $cacheKey
     * @return void
     */
    public static function clearCachedBlurImageUrl(string $cacheKey): void
    {
        $storagePath = Cache::get($cacheKey);
        $filesystem = config('filesystems.default');
        if ($storagePath && Storage::disk($filesystem)->exists($storagePath)) {
            Storage::disk($filesystem)->delete($storagePath);
        }
        Cache::forget($cacheKey);
    }
}
