<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\AdminLogs;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function update(string $id, Request $request): JsonResponse
    {
        $product = Product::query()->findOrFail($id);
        $validated = $request->validate([
            "title" => ["sometimes", "string", "max:255"],
            "description" => ["sometimes", "string"],
            "features" => ["sometimes", "array"],
            "features.*" => ["required_with:features", "string", "max:255"],
            "image_1" => ["sometimes", "image", "mimes:jpeg,png,jpg,gif,svg,webp", "max:2048"],
            "image_2" => ["sometimes", "image", "mimes:jpeg,png,jpg,gif,svg,webp", "max:2048"],
            "image_3" => ["sometimes", "image", "mimes:jpeg,png,jpg,gif,svg,webp", "max:2048"],
            "subcategory_id" => ["sometimes", "integer", "exists:categories,id"],
            "show_in_home_page" => ["sometimes", "boolean"],
            "stock" => ["sometimes" | "integer", "min:0"],
            "before_discount_price" => ["sometimes", "numeric", "min:0"],
            "discount_percentage" => ["sometimes", "numeric", "min:0", "max:100"],
            "sold_count" => ["sometimes", "integer", "min:0"],
        ]);

        $validated = $this->getValidated($request, $validated);

        $oldImages = [];
        $imageFields = ['image_1', 'image_2', 'image_3'];
        foreach ($imageFields as $field) {
            if (isset($validated[$field])) {
                $oldImages[$field] = $product->$field;
            }
        }

        $old_stock = $product->stock;
        $old_before_discount_price = $product->before_discount_price;
        $old_discount_percentage = $product->discount_percentage;
        $product->update($validated);

        foreach ($oldImages as $oldImagePath) {
            if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                Storage::disk('public')->delete($oldImagePath);
            }
        }

        $product->load("subcategory");

        // Log
        if (isset($validated["stock"]) && $validated["stock"] != $old_stock) {
            AdminLogs::query()->create([
                "type" => "inventory_changes",
                "action" => $validated["stock"] >= $old_stock ? "add_stock" : "remove_stock",
                "data" => [
                    "product_id" => $product->id,
                    "stock" => $validated["stock"],
                    "quantity" => abs($validated["stock"] - $old_stock),
                    "timestamp" => time()
                ]
            ]);
        }
        if (isset($validated["before_discount_price"]) && $validated["before_discount_price"] != $old_before_discount_price) {
            AdminLogs::query()->create([
                "type" => "inventory_changes",
                "action" => $validated["before_discount_price"] >= $old_before_discount_price ? "increase_price" : "decrease_price",
                "data" => [
                    "product_id" => $product->id,
                    "before_discount_price" => $validated["before_discount_price"],
                    "amount" => abs($validated["before_discount_price"] - $old_before_discount_price),
                    "timestamp" => time()
                ]
            ]);
        }
        if (isset($validated["discount_percentage"]) && $validated["discount_percentage"] != $old_discount_percentage) {
            AdminLogs::query()->create([
                "type" => "inventory_changes",
                "action" => $validated["discount_percentage"] >= $old_discount_percentage ? "increase_discount" : "decrease_discount",
                "data" => [
                    "product_id" => $product->id,
                    "discount_percentage" => $validated["discount_percentage"],
                    "amount" => abs($validated["discount_percentage"] - $old_discount_percentage),
                    "timestamp" => time()
                ]
            ]);
        }

        // Log
        AdminLogs::query()->create([
            "type" => "administrative_actions",
            "action" => "product_modification",
            "data" => [
                "product_id" => $product->id,
                "changes" => $validated,
                "timestamp" => time()
            ]
        ]);

        return response()->json(["message" => "Product has been updated successfully"]);
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['required_with:features', 'string', 'max:255'],
            'image_1' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'image_2' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'image_3' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'subcategory_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'show_in_home_page' => ['sometimes', 'boolean'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'before_discount_price' => ['sometimes', 'numeric', 'min:0'],
            'discount_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'sold_count' => ['sometimes', 'integer', 'min:0'],
        ]);

        $validated = $this->getValidated($request, $validated);
        $product = Product::query()->create($validated);

        $product->load("subcategory");

        // Log
        AdminLogs::query()->create([
            "type" => "administrative_actions",
            "action" => "product_addition",
            "data" => [
                "product_id" => $product->id,
                "timestamp" => time()
            ]
        ]);

        return response()->json(ProductResource::make($product));
    }

    public function softDelete(string $id): JsonResponse
    {
        $product = Product::query()->findOrFail($id);

        // Log
        AdminLogs::query()->create([
            "type" => "inventory_changes",
            "action" => "status_change",
            "data" => [
                "product_id" => $product->id,
                "status" => "Inactive",
                "timestamp" => time()
            ]
        ]);

        $product->delete();
        return response()->json(["message" => "Product has been soft deleted"]);
    }

    public function restore(string $id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($id);

        // Log
        AdminLogs::query()->create([
            "type" => "inventory_changes",
            "action" => "status_change",
            "data" => [
                "product_id" => $product->id,
                "status" => "Active",
                "timestamp" => time()
            ]
        ]);

        $product->restore();
        return response()->json(["message" => "Product has been restored"]);
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::withTrashed()->findOrFail($id);
        $oldImages = [];
        foreach (['image_1', 'image_2', 'image_3'] as $field) {
            $oldImages[$field] = $product->$field;
        }
        foreach ($oldImages as $oldImagePath) {
            if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                Storage::disk('public')->delete($oldImagePath);
            }
        }

        // Log
        AdminLogs::query()->create([
            "type" => "administrative_actions",
            "action" => "product_deletion",
            "data" => [
                "product_id" => $product->id,
                "timestamp" => time()
            ]
        ]);

        $product->forceDelete();
        return response()->json(["message" => "Product has been permanently deleted"]);
    }

    public function getById(string $id): JsonResponse
    {
        $product = Product::query()->findOrFail($id);
        $product->load("subcategory");
        return response()->json(ProductResource::make($product));
    }

    public function most_sold(): JsonResponse
    {
        $products = Product::query()->with("subcategory")->orderBy('sold_count', 'desc')->limit(20)->get();
        return response()->json(ProductResource::collection($products));
    }

    public function get(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "search" => ["sometimes", "string"],
            "subcategory_id" => ["sometimes", "integer", "exists:categories,id"],
            "category_id" => ["sometimes", "integer", "exists:categories,id"],
            "order_by" => ["sometimes", "string", "in:most_sold,most_expensive,least_expensive"],
        ]);
        $query = Product::with("subcategory");
        return $this->filter_query($validated, $query);
    }

    public function get_trashed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "search" => ["sometimes", "string"],
            "subcategory_id" => ["sometimes", "integer", "exists:categories,id"],
            "category_id" => ["sometimes", "integer", "exists:categories,id"],
            "order_by" => ["sometimes", "string", "in:most_sold,most_expensive,least_expensive"],
        ]);
        $query = Product::withTrashed()->with("subcategory")->whereNotNull("deleted_at");
        return $this->filter_query($validated, $query);
    }

    public function one_per_category(): JsonResponse
    {
        $categories = Category::query()->whereNull('parent_id')->with('subCategories')->get()->map(function ($category) {
            return Product::query()->whereIn('subcategory_id', $category->subCategories->pluck('id'))->inRandomOrder()->first();
        })->filter();
        return response()->json(ProductResource::collection($categories));
    }

    public function show_in_home_page(): JsonResponse
    {
        $products = Product::query()->with("subcategory")->where('show_in_home_page', true)->get();
        return response()->json(ProductResource::collection($products));
    }

    /**
     * @param array $validated
     * @param Builder $query
     * @return JsonResponse
     */
    public function filter_query(array $validated, Builder $query): JsonResponse
    {
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('features', 'like', '%' . $search . '%');
            });
        }

        if (!empty($validated['subcategory_id'])) {
            $query->where('subcategory_id', $validated['subcategory_id']);
        }

        if (!empty($validated['category_id'])) {
            $query->whereHas('subcategory', function ($q) use ($validated) {
                $q->where('parent_id', $validated['category_id']);
            });
        }

        if (isset($validated['order_by'])) {
            switch ($validated['order_by']) {
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
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $products = $query->get();
        return response()->json(ProductResource::collection($products));
    }
}
