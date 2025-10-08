<?php

namespace App\Http\Controllers;

use App\Http\Resources\DiscountResource;
use App\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'unique:discounts', 'string'],
            'discount_percentage' => ['required', 'numeric', 'between:1,100'],
            'max_amount' => ['sometimes', 'numeric', 'nullable'],
            'expires_at' => ['sometimes', 'date_format:Y-m-d H:i:s', 'nullable', 'before:2038-01-19'],
        ]);
        $discount = Discount::query()->create($validated);
        return response()->json(DiscountResource::make($discount));
    }

    public function get_by_id(string $id): JsonResponse
    {
        $discount = Discount::query()->findOrFail($id);
        return response()->json(DiscountResource::make($discount));
    }

    public function softDelete(string $id): JsonResponse
    {
        $discount = Discount::query()->findOrFail($id);
        $discount->delete();
        return response()->json(["message" => "Discount has been soft deleted"]);
    }

    public function restore(string $id): JsonResponse
    {
        $discount = Discount::withTrashed()->findOrFail($id);
        $discount->restore();
        return response()->json(["message" => "Discount has been restored"]);
    }

    public function destroy(string $id): JsonResponse
    {
        Discount::withTrashed()->where('id', $id)->forceDelete();
        return response()->json(["message" => "Discount has been permanently deleted"]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'discount_percentage' => ['sometimes', 'numeric', 'between:1,100'],
            'max_amount' => ['sometimes', 'numeric', 'nullable'],
            'expires_at' => ['sometimes', 'date_format:Y-m-d H:i:s', 'nullable', 'before:2038-01-19'],
        ]);
        $discount = Discount::query()->findOrFail($id);
        $discount->update($validated);
        return response()->json(DiscountResource::make($discount));
    }

    public function trashed_discounts(): JsonResponse
    {
        $discounts = Discount::withTrashed()->whereNotNull('deleted_at')->get();
        return response()->json(DiscountResource::collection($discounts));
    }

    public function get(): JsonResponse
    {
        $discounts = Discount::query()->get();
        return response()->json(DiscountResource::collection($discounts));
    }

    public function get_by_code(string $code): JsonResponse
    {
        $discount = Discount::query()->firstWhere('code', $code);

        if (!$discount) {
            return response()->json([
                'message' => 'Discount not found'
            ], 404);
        }

        return response()->json([
            'data' => DiscountResource::make($discount)
        ]);
    }
}
