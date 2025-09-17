<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function update(string $id, Request $request)
    {
        $product = Product::query()->findOrFail($id);
        $validated = $request->validate([
            "title" => "sometimes|string|max:255",
            "description" => "sometimes|string",
            "features" => "sometimes|array",
            "features.*" => "required_with:features|string|max:255",
            "image_1" => "sometimes|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            "image_2" => "sometimes|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            "image_3" => "sometimes|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            "subcategory_id" => "sometimes|integer|exists:categories,id",
            "show_in_home_page" => "sometimes|boolean",
            "stock" => "sometimes|integer|min:0",
            "before_discount_price" => "sometimes|numeric|min:0",
            "discount_percentage" => "sometimes|numeric|min:0|max:100",
            "sold_count" => "sometimes|integer|min:0",
        ]);

        $validated = $this->getValidated($request, $validated);

        $oldImages = [];
        $imageFields = ['image_1', 'image_2', 'image_3'];
        foreach ($imageFields as $field) {
            if (isset($validated[$field])) {
                $oldImages[$field] = $product->$field;
            }
        }

        $product->update($validated);

        foreach ($oldImages as $field => $oldImagePath) {
            if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                Storage::disk('public')->delete($oldImagePath);
            }
        }

        $product->load("subcategory");
        return response()->json(ProductResource::make($product));
    }

    public function getValidated(Request $request, array $validated): array
    {
        $imageFields = ['image_1', 'image_2', 'image_3'];
        foreach ($imageFields as $field) {
            if ($request->hasFile($field)) {
                $validated[$field] = $request->file($field)->store('products', 'public');
            }
        }

        if (isset($validated['features'])) {
            $validated['features'] = json_encode($validated['features']);
        } else {
            $validated['features'] = json_encode([]);
        }

        return $validated;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'features' => 'sometimes|array',
            'features.*' => 'required_with:features|string|max:255',
            'image_1' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'image_2' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'image_3' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'subcategory_id' => 'sometimes|integer|exists:categories,id',
            'show_in_home_page' => 'sometimes|boolean',
            'stock' => 'sometimes|integer|min:0',
            'before_discount_price' => 'sometimes|numeric|min:0',
            'discount_percentage' => 'sometimes|numeric|min:0|max:100',
            'sold_count' => 'sometimes|integer|min:0',
        ]);

        $validated = $this->getValidated($request, $validated);
        $product = Product::query()->create($validated);

        $product->load("subcategory");
        return response()->json(ProductResource::make($product));
    }

    public function softDelete(string $id)
    {
        $product = Product::query()->findOrFail($id);
        $product->delete();
        return response()->json(["message" => "Product has been soft deleted"]);
    }

    public function restore(string $id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        return response()->json(["message" => "Product has been restored"]);
    }

    public function destroy(string $id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $oldImages = [];
        foreach (['image_1', 'image_2', 'image_3'] as $field) {
            $oldImages[$field] = $product->$field;
        }
        foreach ($oldImages as $field => $oldImagePath) {
            if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                Storage::disk('public')->delete($oldImagePath);
            }
        }
        $product->forceDelete();
        return response()->json(["message" => "Product has been permanently deleted"]);
    }

    public function getById(string $id)
    {
        $product = Product::query()->findOrFail($id);
        $product->load("subcategory");
        return response()->json(ProductResource::make($product));
    }

    public function most_sold()
    {
        $products = Product::query()->with("subcategory")->orderBy('sold_count', 'desc')->limit(20)->get();
        return response()->json(ProductResource::collection($products));
    }

    public function get(Request $request)
    {
        $validated = $request->validate([
            "search" => "sometimes|string",
            "subcategory_id" => "sometimes|integer|exists:categories,id",
            "category_id" => "sometimes|integer|exists:categories,id",
            "order_by" => "sometimes|string|in:most_sold,most_expensive,least_expensive",
        ]);
        $query = Product::with("subcategory");
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('features', 'like', '%' . $search . '%');
            });
        }
        if ($request->has('subcategory_id') && !empty($request->subcategory_id)) {
            $query->where('subcategory_id', $request->subcategory_id);
        }
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->whereHas('subcategory', function ($q) use ($request) {
                $q->where('parent_id', $request->category_id);
            });
        }
        switch ($request->order_by) {
            case 'most_sold':
                $query->orderBy('sold_count', 'desc');
                break;
            case 'least_expensive':
                $query->orderBy('price');
                break;
            case 'most_expensive':
                $query->orderBy('price', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }
        $products = $query->get();
        return response()->json(ProductResource::collection($products));
    }

    public function get_trashed(Request $request)
    {
        $validated = $request->validate([
            "search" => "sometimes|string",
            "subcategory_id" => "sometimes|integer|exists:categories,id",
            "category_id" => "sometimes|integer|exists:categories,id",
            "order_by" => "sometimes|string|in:most_sold,most_expensive,least_expensive",
        ]);
        $query = Product::withTrashed()->with("subcategory")->whereNotNull("deleted_at");
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('features', 'like', '%' . $search . '%');
            });
        }
        if ($request->has('subcategory_id') && !empty($request->subcategory_id)) {
            $query->where('subcategory_id', $request->subcategory_id);
        }
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->whereHas('subcategory', function ($q) use ($request) {
                $q->where('parent_id', $request->category_id);
            });
        }
        switch ($request->order_by) {
            case 'most_sold':
                $query->orderBy('sold_count', 'desc');
                break;
            case 'least_expensive':
                $query->orderBy('price');
                break;
            case 'most_expensive':
                $query->orderBy('price', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }
        $products = $query->get();
        return response()->json(ProductResource::collection($products));
    }

    public function one_per_category()
    {
        $categories = Category::query()->whereNull('parent_id')->with('subCategories')->get()->map(function ($category) {
            return Product::query()->whereIn('subcategory_id', $category->subCategories->pluck('id'))->inRandomOrder()->first();
        })->filter();
        return response()->json(ProductResource::collection($categories));
    }

    public function show_in_home_page()
    {
        $products = Product::query()->with("subcategory")->where('show_in_home_page', true)->get();
        return response()->json(ProductResource::collection($products));
    }
}
