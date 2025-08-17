<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Border;
use App\Models\BorderCategory;
use Illuminate\Http\JsonResponse;

class BorderController extends Controller
{
    public function index(): JsonResponse
    {
        $borders = Border::with('category')
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function ($border) {
                return [
                    'id' => $border->id,
                    'slug' => $border->slug,
                    'name' => $border->name,
                    'aspect_ratio' => $border->aspect_ratio,
                    'preview_url' => asset('storage/' . $border->preview_path),
                    'category_id' => $border->category_id,
                    'category' => $border->category ? [
                        'id' => $border->category->id,
                        'name' => $border->category->name,
                        'slug' => $border->category->slug,
                    ] : null,
                    'is_active' => $border->is_active,
                ];
            });

        return response()->json($borders);
    }

    public function show(Border $border): JsonResponse
    {
        $border->load('category');
        
        return response()->json([
            'id' => $border->id,
            'slug' => $border->slug,
            'name' => $border->name,
            'aspect_ratio' => $border->aspect_ratio,
            'preview_url' => asset('storage/' . $border->preview_path),
            'file_url' => asset('storage/' . $border->file_path),
            'manifest' => $border->manifest,
            'category_id' => $border->category_id,
            'category' => $border->category ? [
                'id' => $border->category->id,
                'name' => $border->category->name,
                'slug' => $border->category->slug,
            ] : null,
            'is_active' => $border->is_active,
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = BorderCategory::withCount('borders')
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'borders_count' => $category->borders_count,
                ];
            });

        return response()->json($categories);
    }
}
