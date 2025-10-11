<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\AdminLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'description' => ['required', 'string'],
            'province' => ['required', 'string'],
            'city' => ['required', 'string'],
        ]);
        $address = Address::query()->create($validated);

        // Log
        AdminLogs::query()->create([
            "type" => "address_management",
            "action" => "create_address",
            "data" => [
                "user_id" => $address->user_id,
                "description" => $address->description,
                "province" => $address->province,
                "city" => $address->city,
                "timestamp" => time()
            ]
        ]);

        return response()->json(AddressResource::make($address));
    }

    public function getById(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json(AddressResource::collection($user->addresses));
    }

    public function update(Request $request, string $id): JsonResponse
    {

        $validated = $request->validate([
            'description' => ['sometimes', 'string'],
            'province' => ['sometimes', 'string'],
            'city' => ['sometimes', 'string']
        ]);
        $address = Address::query()->findOrFail($id);
        $old_description = $address->description;
        $old_province = $address->province;
        $old_city = $address->city;
        $address->update($validated);

        // Log
        AdminLogs::query()->create([
            "type" => "address_management",
            "action" => "update_address",
            "data" => [
                "user_id" => $address->user_id,
                "description" => $address->description,
                "province" => $address->province,
                "city" => $address->city,
                "old_data" => [
                    "description" => $old_description,
                    "province" => $old_province,
                    "city" => $old_city
                ],
                "timestamp" => time()
            ]
        ]);

        return response()->json(["message" => "Address deleted successfully"]);
    }

    public function destroy(string $id): JsonResponse
    {
        $address = Address::query()->findOrFail($id);
        // Log
        AdminLogs::query()->create([
            "type" => "address_management",
            "action" => "delete_address",
            "data" => [
                "user_id" => $address->user_id,
                "description" => $address->description,
                "province" => $address->province,
                "city" => $address->city,
                "timestamp" => time()
            ]
        ]);

        Address::query()
            ->where('id', $id)
            ->delete();

        return response()->json(["message" => "address deleted successfully"]);
    }

    public function get(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'page' => ['required', 'integer', 'min:1'],
            'limit' => ["sometimes", "integer", "min:1"],
        ]);
        $query = Address::with('user');
        if ($request->has('user_id')) {
            $query->where('user_id', $validated['user_id']);
        }
        $limit = $validated['limit'] ?? 10;
        $addresses = $query->paginate($limit, page: $validated['page']);
        $total_pages = ceil($addresses->total() / $limit);
        $pagination_info = [
            "total_items" => $addresses->total(),
            "total_pages" => $total_pages,
            "current_page" => $validated["page"],
            "per_page" => $limit,
            "has_next_page" => ($total_pages - $validated['page']) > 0,
            "has_previous_page" => ($validated['page'] - 1) > 0,
            "next_page" => $validated['page'] + 1,
            "previous_page" => $validated['page'] - 1
        ];
        return response()->json(["data" => AddressResource::collection($addresses), "pagination_info" => $pagination_info]);
    }
}
