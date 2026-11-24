<?php

namespace App\Services\Portfolio;

use App\Exceptions\MarketplaceException;
use App\Models\{Portfolio, PortfolioMedia, User};
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PortfolioService
{
    public function __construct(private SubscriptionService $subs) {}

    public function create(User $user, array $data): Portfolio
    {
        $owner = $user->hasRole('studio') ? $user->studio : $user->photographerProfile;
        if (! $owner) { throw new MarketplaceException('Complete your profile first.', 'profile_required', 422); }

        return $owner->portfolios()->create($data + ['slug' => Str::slug($data['title']) . '-' . Str::lower(Str::random(6))]);
    }

    /** @param UploadedFile[] $files */
    public function addMedia(User $user, Portfolio $portfolio, array $files): array
    {
        $this->subs->assertPortfolioQuota($user, count($files));
        $disk = Storage::disk(config('marketplace.media.disk'));
        $saved = [];

        foreach ($files as $i => $file) {
            $this->assertSafe($file);
            $isVideo = str_starts_with($file->getMimeType(), 'video/');
            // Random server-side name: never trust the client filename/extension.
            $name = Str::uuid() . '.' . ($isVideo ? $file->guessExtension() : 'jpg');
            $path = "portfolios/{$portfolio->id}/{$name}";

            $stream = $isVideo ? fopen($file->getRealPath(), 'r') : $this->reencodeImage($file);
            $disk->put($path, $stream, ['visibility' => 'private']);
            if (is_resource($stream)) { fclose($stream); }

            $media = $portfolio->media()->create([
                'kind' => $isVideo ? 'video' : 'image', 'path' => $path, 'mime' => $isVideo ? $file->getMimeType() : 'image/jpeg',
                'size_bytes' => $file->getSize(), 'sort_order' => $portfolio->media()->count() + $i,
            ]);
            // Queue watermark + thumbnail generation off the request path.
            \App\Jobs\ProcessPortfolioMedia::dispatch($media->id);
            $saved[] = $media;
        }
        return $saved;
    }

    /** Short-lived signed URL for private / original media. */
    public function signedUrl(PortfolioMedia $m): string
    {
        $disk = Storage::disk(config('marketplace.media.disk'));
        $path = $m->watermarked_path ?? $m->path;
        return $disk->temporaryUrl($path, now()->addMinutes(config('marketplace.media.signed_url_minutes')));
    }

    private function assertSafe(UploadedFile $f): void
    {
        $max = config('marketplace.media');
        $mime = $f->getMimeType();   // sniffed from content, not the client header
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime'];

        if (! $f->isValid() || ! in_array($mime, $allowed, true)) {
            throw new MarketplaceException('Unsupported or corrupt file.', 'bad_upload', 422);
        }
        $kb = $f->getSize() / 1024;
        if ((str_starts_with($mime, 'image/') && $kb > $max['max_image_kb']) || (str_starts_with($mime, 'video/') && $kb > $max['max_video_kb'])) {
            throw new MarketplaceException('File is too large.', 'file_too_large', 422);
        }
    }

    /**
     * Decode + re-encode through GD. This strips EXIF/GPS and any polyglot payload hidden in the file,
     * which is the "sanitize media" requirement. Returns a stream.
     */
    private function reencodeImage(UploadedFile $f)
    {
        $img = @imagecreatefromstring(file_get_contents($f->getRealPath()));
        if (! $img) { throw new MarketplaceException('Image could not be processed.', 'bad_image', 422); }
        $tmp = fopen('php://temp', 'w+');
        ob_start(); imagejpeg($img, null, 88); $bytes = ob_get_clean();
        imagedestroy($img);
        fwrite($tmp, $bytes); rewind($tmp);
        return $tmp;
    }
}
