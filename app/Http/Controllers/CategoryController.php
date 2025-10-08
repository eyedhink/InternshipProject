<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'unique:categories', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id']
        ]);

        $category = Category::query()->create($validated);

        return response()->json(CategoryResource::make($category));
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'unique:categories', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id']
        ]);

        $category = Category::query()->find($id);
        $category->update($validated);

        return response()->json(CategoryResource::make($category));
    }

    public function getById(string $id): JsonResponse
    {
        $category = Category::with(['subCategories', 'parent'])->find($id);
        $category->all_products;
        return response()->json(CategoryResource::make($category));
    }

    public function get(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "parent_id" => ["sometimes", "nullable", "exists:categories,id"],
            "is_main" => ["sometimes", "nullable", "boolean"]
        ]);
        $query = Category::with(['subCategories', 'parent']);
        if (isset($validated['parent_id'])) {
            $query->where('parent_id', $validated['parent_id']);
        } else if (isset($validated['is_main']) && $validated['is_main']) {
            $query->whereNull('parent_id');
        } else if (isset($validated['is_main'])) {
            $query->whereNotNull('parent_id');
        }

        $categories = $query->get();

        return response()->json(CategoryResource::collection($categories));
    }

    function destroy(string $id): JsonResponse
    {
        Category::query()->where('id', $id)->delete();
        return response()->json(["message" => "Category deleted successfully"]);
    }
}
