<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundraisingContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundraisingContributionsAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contribs = FundraisingContribution::query()
            ->orderByDesc('paid_at')
            ->paginate((int) $request->get('per_page', 50));

        return response()->json($contribs);
    }
}

