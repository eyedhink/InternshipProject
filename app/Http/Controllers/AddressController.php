<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\AdminLogs;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'description' => 'required|string',
            'province' => 'required|string',
            'city' => 'required|string',
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

    public function getById(Request $request)
    {
        $user = $request->user('sanctum');
        return response()->json(AddressResource::collection($user->addresses));
    }

    public function update(Request $request, string $id)
    {
        $address = Address::query()->findOrFail($id);
        $validated = $request->validate([
            'description' => 'sometimes|string',
            'province' => 'sometimes|string',
            'city' => 'sometimes|string',
        ]);
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

        return response()->json(AddressResource::make($address));
    }

    public function destroy(string $id)
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

        $address->delete();
        return response()->json(["message" => "address deleted successfully"]);
    }

    public function get(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|integer|exists:users,id',
        ]);
        $query = Address::with('user');
        if ($request->has('user_id')) {
            $query->where('user_id', $validated['user_id']);
        }
        $addresses = $query->get();
        return response()->json(AddressResource::collection($addresses));
    }
}
