<?php

namespace App\Services;

use App\Jobs\AddWatermarkJob;
use App\Jobs\OptimizeImageJob;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class FileService
{
    private const PROPERTY_IMAGE_WIDTH = 1500;

    private const PROPERTY_IMAGE_HEIGHT = 1026;

    private const PROPERTY_TITLE_TARGET_BYTES = 220000;

    private const PROPERTY_TITLE_QUALITY_MAX = 94;

    private const PROPERTY_TITLE_QUALITY_MIN = 88;

    private const PROPERTY_GALLERY_TARGET_BYTES = 146559;

    private const PROPERTY_GALLERY_QUALITY_MAX = 92;

    private const PROPERTY_GALLERY_QUALITY_MIN = 82;

    private const PROPERTY_QUALITY_STEP = 2;

    /**
     * Compress and upload an image (optional watermark)
     *
     * @return string|false
     */
    public static function compressAndUpload($requestFile, string $folder, bool $addWaterMark = false)
    {
        $filenameWithoutExt = pathinfo($requestFile->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($requestFile->getClientOriginalExtension());
        if (empty($extension)) {
            $mime = $requestFile->getMimeType();
            $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
            $extension = $map[$mime] ?? 'jpg';
        }
        $fileName = time().'-'.Str::slug($filenameWithoutExt).'.'.$extension;
        $disk = 'public';
        $path = $folder.''.$fileName;

        try {
            Log::info('FileService::compressAndUpload: Starting', ['file' => $fileName, 'extension' => $extension, 'disk' => $disk, 'path' => $path]);

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                // Compress and save image
                try {
                    Log::info('FileService::compressAndUpload: Calling Image::make');
                    $image = Image::make($requestFile);
                    $propertyImageProfile = self::getPropertyImageProfile($folder);

                    if (! empty($propertyImageProfile)) {
                        // Keep composition and avoid quality loss from forced crop/upscale.
                        $image->resize(self::PROPERTY_IMAGE_WIDTH, self::PROPERTY_IMAGE_HEIGHT, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });

                        $image = self::encodeWithTargetSize(
                            $image,
                            $extension,
                            $propertyImageProfile['target_bytes'],
                            $propertyImageProfile['quality_max'],
                            $propertyImageProfile['quality_min'],
                            self::PROPERTY_QUALITY_STEP
                        );
                    } else {
                        $image = $image->encode($extension, 80);
                    }
                    Log::info('FileService::compressAndUpload: Image made');
                } catch (\Throwable $e) {
                    Log::error('FileService::compressAndUpload: Image::make failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    // Fallback to simple upload if compression fails
                    $requestFile->storeAs($folder, $fileName, $disk);

                    return $fileName;
                }

                $putResult = Storage::disk($disk)->put($path, (string) $image);

                if (! $putResult) {
                    Log::error('FileService::compressAndUpload: Storage::put failed', ['path' => $path]);

                    return false;
                }

                Log::info('FileService::compressAndUpload: Image saved', ['path' => $path]);

                // Get absolute path safely
                $absolutePath = self::getAbsolutePath($disk, $path);
                if (! $absolutePath) {
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
                        'size' => $fileSize,
                    ]);
                }

                // Queue watermark if enabled
                if ($addWaterMark && HelperService::getWatermarkConfigStatus()) {
                    AddWatermarkJob::dispatch($absolutePath, $extension)->delay(now()->addSeconds(5)); // small safety delay
                }

                return $fileName;
            }

            if (in_array($extension, ['pdf'])) {
                $minSizeForCompression = 512 * 1024; // skip PDFs under 512KB
                $tempInput = $requestFile->getPathname();

                if (filesize($tempInput) > $minSizeForCompression) {
                    $tempOutput = tempnam(sys_get_temp_dir(), 'pdf_compressed_') . '.pdf';

                    $gsCommand = sprintf(
                        'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
                        escapeshellarg($tempOutput),
                        escapeshellarg($tempInput)
                    );

                    Log::info('FileService::compressAndUpload: Compressing PDF', ['input' => $tempInput, 'size' => filesize($tempInput)]);

                    exec($gsCommand, $gsOutput, $exitCode);

                    if ($exitCode === 0 && file_exists($tempOutput) && filesize($tempOutput) > 0 && filesize($tempOutput) < filesize($tempInput)) {
                        Log::info('FileService::compressAndUpload: PDF compressed', [
                            'original' => filesize($tempInput),
                            'compressed' => filesize($tempOutput),
                            'ratio' => round(filesize($tempOutput) / filesize($tempInput) * 100, 1) . '%',
                        ]);

                        Storage::disk($disk)->put($path, file_get_contents($tempOutput));
                    } else {
                        Log::warning('FileService::compressAndUpload: PDF compression failed or no reduction, using original', [
                            'exitCode' => $exitCode,
                            'gsOutput' => implode("\n", $gsOutput),
                        ]);
                        $requestFile->storeAs($folder, $fileName, $disk);
                    }

                    if (file_exists($tempOutput)) {
                        unlink($tempOutput);
                    }

                    return $fileName;
                }
            }

            // Non-image files (PDFs under threshold or other types)
            $requestFile->storeAs($folder, $fileName, $disk);

            return $fileName;

        } catch (Exception $e) {
            Log::error('FileService::compressAndUpload error: '.$e->getMessage(), ['file' => $path]);

            return false;
        }
    }

    /**
     * Simple upload (non-compressed)
     */
    public static function upload($requestFile, string $folder): string
    {
        $extension = strtolower($requestFile->getClientOriginalExtension());
        if (empty($extension)) {
            $mime = $requestFile->getMimeType();
            $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
            $extension = $map[$mime] ?? 'jpg';
        }
        $fileName = uniqid('', true).time().'.'.$extension;
        $requestFile->storeAs($folder, $fileName, 'public');

        return $folder.'/'.$fileName;
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
        if (! empty($deleteRawOriginalImage)) {
            self::delete($folder, $deleteRawOriginalImage);
        }

        return self::compressAndUpload($requestFile, $folder, $addWaterMark);
    }

    /**
     * File Exists
     */
    public static function fileExists(string $filePath): bool
    {
        $disk = 'public';
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

                // if (!file_exists($absolutePath)) {
                //     Log::info('FileService::getAbsolutePath: File not found', ['path' => $absolutePath, 'filePath' => $filePath, 'disk' => $disk]);
                // }
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
        if (self::fileExists($imagePath)) {
            return Storage::disk('public')->url($imagePath);
        }

        return null;
    }

    /**
     * Delete a file if exists
     */
    public static function delete(string $folder, $image): bool
    {
        if (! empty($image) && Storage::disk('public')->exists($folder.'/'.$image)) {
            return Storage::disk('public')->delete($folder.'/'.$image);
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
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $extension = 'jpg'; // fallback
        }

        // encode to original format
        $img->encode($extension, $quality);

        return base64_encode($img);
    }

    public static function getBlurDataUri(string $path, int $width = 20, int $height = 20, int $blur = 10, int $quality = 50): string
    {
        $filesystem = 'public';
        $storagePath = self::getAbsolutePath($filesystem, ltrim($path, '/'));
        if (! $storagePath) {
            Log::info('File not found: '.$path);

            return false;
        }

        $base64 = self::generateBlurData($storagePath, $width, $height, $blur, $quality);

        // Detect mime type
        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.$base64;
    }

    private static function getPropertyImageProfile(string $folder): ?array
    {
        $normalizedFolder = trim($folder, '/');
        $propertyTitlePath = trim(config('global.PROPERTY_TITLE_IMG_PATH'), '/');
        $propertyGalleryPath = trim(config('global.PROPERTY_GALLERY_IMG_PATH'), '/');

        if (str_starts_with($normalizedFolder, $propertyTitlePath)) {
            return [
                'target_bytes' => self::PROPERTY_TITLE_TARGET_BYTES,
                'quality_max' => self::PROPERTY_TITLE_QUALITY_MAX,
                'quality_min' => self::PROPERTY_TITLE_QUALITY_MIN,
            ];
        }

        if (str_starts_with($normalizedFolder, $propertyGalleryPath)) {
            return [
                'target_bytes' => self::PROPERTY_GALLERY_TARGET_BYTES,
                'quality_max' => self::PROPERTY_GALLERY_QUALITY_MAX,
                'quality_min' => self::PROPERTY_GALLERY_QUALITY_MIN,
            ];
        }

        return null;
    }

    private static function encodeWithTargetSize($image, string $extension, int $targetBytes, int $qualityMax, int $qualityMin, int $qualityStep)
    {
        $best = $image->encode($extension, $qualityMax);
        if (strlen((string) $best) <= $targetBytes) {
            return $best;
        }

        for ($quality = $qualityMax - $qualityStep; $quality >= $qualityMin; $quality -= $qualityStep) {
            $candidate = $image->encode($extension, $quality);
            $best = $candidate;

            if (strlen((string) $candidate) <= $targetBytes) {
                break;
            }
        }

        return $best;
    }

    /**
     * Generate or return cached blur image URL
     *
     * @param  string  $imagePath  Full storage path of original image
     * @param  string  $cacheKey  Unique cache key (e.g., 'module_blur_{module}_{id}')
     * @param  string|null  $filename  Optional filename to save
     * @return string|null Public URL
     */
    public static function getCachedBlurImageUrl(string $imagePath, string $cacheKey, ?string $filename = null): ?string
    {
        try {
            $filename = $filename ?? 'blur_'.basename($imagePath);
            $storagePath = "blur_cache/{$filename}";
            $filesystem = 'public';

            if (! Cache::has($cacheKey) || ! Storage::disk($filesystem)->exists($storagePath)) {

                $originalImagePath = self::getAbsolutePath($filesystem, $imagePath);
                if (! file_exists($originalImagePath)) {
                    return null;
                }

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
            Log::error('FileService getCachedBlurImageUrl error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Clear cached blur image
     */
    public static function clearCachedBlurImageUrl(string $cacheKey): void
    {
        $storagePath = Cache::get($cacheKey);
        $filesystem = 'public';
        if ($storagePath && Storage::disk($filesystem)->exists($storagePath)) {
            Storage::disk($filesystem)->delete($storagePath);
        }
        Cache::forget($cacheKey);
    }
}
