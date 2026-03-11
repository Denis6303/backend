<?php

namespace App\Http\Controllers\Api\V1\Category;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;

class CategoryController extends Controller
{
    /**
     * @group Category
     *
     * List all available event categories.
     *
     * This endpoint returns the full list of categories that can be used
     * when creating or filtering events.
     *
     * @response 200 scenario="Success" {
     *   "success": true,
     *   "code": 0,
     *   "locale": "en",
     *   "message": "Category list retrieved successfully",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Concert",
     *       "name_en": "Concert",
     *       "description": "Music concerts and live shows."
     *     }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()->orderBy('name')->get();

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.categories.retrieved'))
            ->withData($categories)
            ->build();
    }
}

