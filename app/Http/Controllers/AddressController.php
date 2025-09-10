<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Models\Address;
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
            'user_id' => 'sometimes|integer|exists:users,id',
            'description' => 'sometimes|string',
            'province' => 'sometimes|string',
            'city' => 'sometimes|string',
        ]);
        $address->update($validated);
        return response()->json(AddressResource::make($address));
    }

    public function destroy(string $id)
    {
        $address = Address::query()->findOrFail($id);
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
