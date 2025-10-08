<?php

namespace App\Http\Controllers;

use App\Http\Resources\HistoryResource;
use App\Models\History;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'action' => ['sometimes', 'string']
        ]);
        $history = History::query()->create($validated);
        return response()->json(HistoryResource::make($history));
    }

    public function get(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'from_created_at' => ['sometimes', 'string'],
            'to_created_at' => ['sometimes', 'string'],
        ]);
        $query = History::with('user');
        if ($request->has('user_id') && !empty($validated->user_id)) {
            $query->where('user_id', $validated->user_id);
        }
        if ($request->has('from_created_at') && !empty($validated->from_created_at)) {
            $query->where('created_at', '>=', $validated->from_created_at);
        }
        if ($request->has('to_created_at') && !empty($validated->to_created_at)) {
            $query->where('created_at', '<=', $validated->to_created_at);
        }
        $histories = $query->get();
        return response()->json(HistoryResource::collection($histories));
    }
}
