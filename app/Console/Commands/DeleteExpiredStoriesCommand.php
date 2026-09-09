<?php

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteExpiredStoriesCommand extends Command
{
    protected $signature   = 'stories:delete-expired';
    protected $description = 'Hard-delete stories whose 24-hour window has passed and remove their media files';

    public function handle(): void
    {
        $stories = Story::where('expires_at', '<=', now())->get();

        $disk    = 'public';
        $deleted = 0;

        foreach ($stories as $story) {
            // Remove media file
            if ($story->media_type === 'image') {
                $path = 'stories/images/' . basename($story->media_url);
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } else {
                $videoPath = 'stories/videos/' . basename($story->media_url);
                if (Storage::disk($disk)->exists($videoPath)) {
                    Storage::disk($disk)->delete($videoPath);
                }
                if ($story->thumbnail_url) {
                    $thumbPath = 'stories/thumbnails/' . basename($story->thumbnail_url);
                    if (Storage::disk($disk)->exists($thumbPath)) {
                        Storage::disk($disk)->delete($thumbPath);
                    }
                }
            }

            $story->delete();
            $deleted++;
        }

        $this->info("Deleted {$deleted} expired stories.");
    }
}
