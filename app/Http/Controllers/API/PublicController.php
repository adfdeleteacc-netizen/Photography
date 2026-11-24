<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{PhotographerProfile, Portfolio, Studio};
use App\Services\Booking\SearchService;
use App\Services\Portfolio\PortfolioService;
use Illuminate\Http\{JsonResponse, Request};

class PublicController extends Controller
{
    public function __construct(private SearchService $search, private PortfolioService $portfolios) {}

    public function photographers(Request $r): JsonResponse
    {
        return response()->json($this->search->search($r->validate($this->filterRules())));
    }

    public function search(Request $r): JsonResponse
    {
        return $this->photographers($r);
    }

    public function studios(Request $r): JsonResponse
    {
        $studios = Studio::query()->where('is_verified', true)
            ->when($r->city, fn ($q, $c) => $q->where('city', $c))
            ->orderByDesc('is_featured')->paginate(20);
        return response()->json($studios);
    }

    public function portfolio(string $slug): JsonResponse
    {
        $p = Portfolio::with(['media' => fn ($q) => $q->where('is_private', false)->whereNotNull('watermarked_path'), 'category', 'owner'])
            ->where('slug', $slug)->where('is_published', true)->firstOrFail();

        // Public pages only ever receive watermarked / thumbnail variants.
        $p->media->each(fn ($m) => $m->url = $this->portfolios->signedUrl($m));
        return response()->json($p);
    }

    public function creator(string $slug): JsonResponse
    {
        $profile = PhotographerProfile::with(['categories', 'packages', 'portfolios' => fn ($q) => $q->where('is_published', true)->latest()->limit(12), 'reviews' => fn ($q) => $q->where('is_visible', true)->latest()->limit(10)])
            ->where('slug', $slug)->firstOrFail();
        return response()->json($profile);
    }

    private function filterRules(): array
    {
        return [
            'city' => 'nullable|string|max:80', 'service_type' => 'nullable|exists:categories,slug',
            'min_budget' => 'nullable|numeric|min:0', 'max_budget' => 'nullable|numeric|min:0',
            'min_rating' => 'nullable|numeric|between:0,5', 'date' => 'nullable|date|after_or_equal:today',
            'verified' => 'nullable|boolean', 'q' => 'nullable|string|max:80',
        ];
    }
}
