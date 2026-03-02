<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fundraising;
use App\Models\FundraisingContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundraisingsAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $fundraisings = Fundraising::query()
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 25));

        return response()->json($fundraisings);
    }

    public function verify(int $id): JsonResponse
    {
        $fundraising = Fundraising::query()->findOrFail($id);
        $fundraising->is_verified = true;
        $fundraising->save();

        return response()->json(['data' => $fundraising->fresh()]);
    }

    public function contributions(int $id, Request $request): JsonResponse
    {
        $fundraising = Fundraising::query()->findOrFail($id);

        $contribs = FundraisingContribution::query()
            ->where('fundraising_id', $fundraising->id)
            ->orderByDesc('paid_at')
            ->paginate((int) $request->get('per_page', 50));

        return response()->json($contribs);
    }
}

