<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\StorePortfolioRequest;
use App\Models\{Portfolio, PortfolioMedia};
use App\Services\Portfolio\PortfolioService;
use Illuminate\Http\{JsonResponse, Request};

class PortfolioController extends Controller
{
    public function __construct(private PortfolioService $portfolios) {}

    public function store(StorePortfolioRequest $r): JsonResponse
    {
        $p = $this->portfolios->create($r->user(), $r->safe()->except('files'));
        if ($r->hasFile('files')) { $this->portfolios->addMedia($r->user(), $p, $r->file('files')); }
        return response()->json($p->load('media'), 201);
    }

    public function addMedia(Request $r, Portfolio $portfolio): JsonResponse
    {
        $this->authorize('update', $portfolio);
        $r->validate(['files' => 'required|array|max:30', 'files.*' => 'file|max:204800']);
        return response()->json($this->portfolios->addMedia($r->user(), $portfolio, $r->file('files')), 201);
    }

    public function destroy(Portfolio $portfolio): JsonResponse
    {
        $this->authorize('delete', $portfolio);
        $portfolio->delete();
        return response()->json(null, 204);
    }

    /** Short-lived signed URL to the ORIGINAL of a private file (owner only). */
    public function privateUrl(Request $r, PortfolioMedia $media): JsonResponse
    {
        $this->authorize('update', $media->portfolio);
        return response()->json(['url' => \Storage::disk(config('marketplace.media.disk'))->temporaryUrl($media->path, now()->addMinutes(config('marketplace.media.signed_url_minutes')))]);
    }
}
