<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Fundraising;
use App\Models\FundraisingContribution;
use App\Services\Search\FundraisingSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundraisingsController extends Controller
{
    public function index(Request $request, FundraisingSearch $search): JsonResponse
    {
        $filters = $request->only(['q', 'status', 'country_code', 'is_private']);

        $fundraisings = $search->query($filters)->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'data' => $fundraisings->getCollection()->map(fn (Fundraising $f) => [
                'id' => $f->id,
                'slug' => $f->slug,
                'title' => $f->title,
                'description' => $f->description,
                'status' => $f->status,
                'currency' => $f->currency,
                'target_amount' => $f->target_amount,
                'current_amount' => $f->current_amount,
                'is_amount_visible' => $f->is_amount_visible,
                'is_private' => $f->is_private,
                'is_verified' => $f->is_verified,
                'country_code' => $f->country_code,
                'likes_count' => $f->likes_count,
                'nb_visites' => $f->nb_visites,
                'cover_url' => $f->getFirstMediaUrl('cover') ?: null,
                'category' => $f->category?->only(['id', 'name', 'slug', 'level']),
            ])->values(),
            'meta' => [
                'current_page' => $fundraisings->currentPage(),
                'last_page' => $fundraisings->lastPage(),
                'per_page' => $fundraisings->perPage(),
                'total' => $fundraisings->total(),
            ],
        ]);
    }

    public function show(string $idOrSlug): JsonResponse
    {
        $query = Fundraising::query()->with(['category']);

        $fundraising = is_numeric($idOrSlug)
            ? $query->findOrFail((int) $idOrSlug)
            : $query->where('slug', $idOrSlug)->firstOrFail();

        $fundraising->increment('nb_visites');

        return response()->json([
            'data' => [
                'id' => $fundraising->id,
                'slug' => $fundraising->slug,
                'title' => $fundraising->title,
                'description' => $fundraising->description,
                'status' => $fundraising->status,
                'currency' => $fundraising->currency,
                'target_amount' => $fundraising->target_amount,
                'current_amount' => $fundraising->current_amount,
                'is_amount_visible' => $fundraising->is_amount_visible,
                'is_private' => $fundraising->is_private,
                'is_verified' => $fundraising->is_verified,
                'country_code' => $fundraising->country_code,
                'likes_count' => $fundraising->likes_count,
                'nb_visites' => $fundraising->nb_visites,
                'cover_url' => $fundraising->getFirstMediaUrl('cover') ?: null,
                'beneficiary_display_name' => $fundraising->beneficiary_display_name,
                'category' => $fundraising->category?->only(['id', 'name', 'slug', 'level']),
                'latest_messages' => $fundraising->latestContributionMessages(),
                'is_goal_reached' => $fundraising->getIsGoalReached(),
            ],
        ]);
    }

    public function contributions(string $idOrSlug, Request $request): JsonResponse
    {
        $fundraising = is_numeric($idOrSlug)
            ? Fundraising::query()->findOrFail((int) $idOrSlug)
            : Fundraising::query()->where('slug', $idOrSlug)->firstOrFail();

        $contribs = FundraisingContribution::query()
            ->where('fundraising_id', $fundraising->id)
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json([
            'data' => $contribs->getCollection()->map(fn (FundraisingContribution $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'amount' => $c->is_amount_visible ? (float) $c->amount : null,
                'message' => $c->message,
                'paid_at' => $c->paid_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $contribs->currentPage(),
                'last_page' => $contribs->lastPage(),
                'per_page' => $contribs->perPage(),
                'total' => $contribs->total(),
            ],
        ]);
    }
}

