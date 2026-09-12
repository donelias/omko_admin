<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BulkImportGalleryController extends Controller
{
    private const GALLERY_FOLDER       = 'bulk-import-gallery';
    private const GALLERY_VIDEO_FOLDER = 'bulk-import-gallery/videos';

    // -----------------------------------------------------------------------
    //  List
    // -----------------------------------------------------------------------

    /**
     * Return all uploaded gallery images with their paths.
     * Path format returned: "bulk-import-gallery/filename.jpg"
     * Users copy this path and paste it into their CSV image column.
     */
    public function index()
    {
        $imageExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $videoExts = ['mp4', 'mov', 'webm', 'avi'];

        $imageFiles = Storage::disk('public')->files(self::GALLERY_FOLDER);
        $videoFiles = Storage::disk('public')->files(self::GALLERY_VIDEO_FOLDER);

        $images = collect($imageFiles)
            ->filter(fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $imageExts))
            ->map(fn ($file) => [
                'path' => $file,
                'url'  => Storage::disk('public')->url($file),
                'name' => basename($file),
            ])->values();

        $videos = collect($videoFiles)
            ->filter(fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $videoExts))
            ->map(fn ($file) => [
                'path' => $file,
                'url'  => Storage::disk('public')->url($file),
                'name' => basename($file),
            ])->values();

        return response()->json(['images' => $images, 'videos' => $videos]);
    }

    // -----------------------------------------------------------------------
    //  Upload
    // -----------------------------------------------------------------------

    /**
     * Upload one or more images to the gallery.
     * Returns the saved paths so the user can copy them into the CSV.
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'images'   => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $uploaded = [];

        foreach ($request->file('images') as $image) {
            // Build a unique filename: timestamp-originalname
            $filename = time().'-'.Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$image->getClientOriginalExtension();

            // Store in public disk under the gallery folder
            $image->storeAs(self::GALLERY_FOLDER, $filename, 'public');

            $uploaded[] = [
                'path' => self::GALLERY_FOLDER.'/'.$filename,
                'url'  => Storage::disk('public')->url(self::GALLERY_FOLDER.'/'.$filename),
                'name' => $filename,
            ];
        }

        return response()->json(['uploaded' => $uploaded]);
    }

    // -----------------------------------------------------------------------
    //  Video Upload
    // -----------------------------------------------------------------------

    public function uploadVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'videos'   => 'required|array|min:1',
            'videos.*' => 'required|file|extensions:mp4,mov,webm|max:102400',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $uploaded = [];

        foreach ($request->file('videos') as $video) {
            $filename = time().'-'.Str::slug(pathinfo($video->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$video->getClientOriginalExtension();

            $video->storeAs(self::GALLERY_VIDEO_FOLDER, $filename, 'public');

            $path = self::GALLERY_VIDEO_FOLDER.'/'.$filename;

            $uploaded[] = [
                'path' => $path,
                'url'  => Storage::disk('public')->url($path),
                'name' => $filename,
            ];
        }

        return response()->json(['uploaded' => $uploaded]);
    }

    // -----------------------------------------------------------------------
    //  Delete
    // -----------------------------------------------------------------------

    /**
     * Delete a gallery file by its full relative path (e.g. bulk-import-gallery/file.jpg
     * or bulk-import-gallery/videos/file.mp4).
     */
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $filePath = $request->path;

        // Security: ensure path stays inside bulk-import-gallery
        if (! str_starts_with($filePath, self::GALLERY_FOLDER.'/')) {
            return response()->json(['error' => 'Invalid path.'], 403);
        }

        if (! Storage::disk('public')->exists($filePath)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        Storage::disk('public')->delete($filePath);

        return response()->json(['success' => true]);
    }
}
