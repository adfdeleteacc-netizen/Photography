<?php

namespace App\Jobs;

use App\Models\PortfolioMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/** Generates thumbnail + watermarked copy for images. Videos are left as-is (hook your transcoder here). */
class ProcessPortfolioMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(public int $mediaId) { $this->onQueue('media'); }

    public function handle(): void
    {
        $m = PortfolioMedia::find($this->mediaId);
        if (! $m || $m->kind !== 'image') { return; }

        $disk = Storage::disk(config('marketplace.media.disk'));
        $src = imagecreatefromstring($disk->get($m->path));
        if (! $src) { return; }

        [$w, $h] = [imagesx($src), imagesy($src)];

        // Thumbnail (max 480px)
        $tw = min(480, $w); $th = (int) round($h * ($tw / $w));
        $thumb = imagescale($src, $tw, $th);
        $m->thumbnail_path = preg_replace('/\.jpg$/', '_thumb.jpg', $m->path);
        $disk->put($m->thumbnail_path, $this->jpeg($thumb), 'public');

        // Watermarked public copy (original stays private for signed-URL access)
        $white = imagecolorallocatealpha($src, 255, 255, 255, 60);
        $text = config('app.name');
        imagestring($src, 5, max($w - (strlen($text) * 10) - 20, 10), max($h - 30, 10), $text, $white);
        $m->watermarked_path = preg_replace('/\.jpg$/', '_wm.jpg', $m->path);
        $disk->put($m->watermarked_path, $this->jpeg($src), 'public');

        $m->save();
        imagedestroy($src); imagedestroy($thumb);
    }

    private function jpeg($img): string
    {
        ob_start(); imagejpeg($img, null, 85); return ob_get_clean();
    }
}
