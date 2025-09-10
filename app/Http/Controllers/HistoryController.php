<?php

namespace App\Http\Controllers;

use App\Http\Resources\HistoryResource;
use App\Models\History;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|integer|min:1',
            'action' => 'sometimes|string'
        ]);
        $history = History::query()->create($validated);
        return response()->json(HistoryResource::make($history));
    }

    public function get(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|integer|exists:users,id',
            'from_created_at' => 'sometimes|string',
            'to_created_at' => 'sometimes|string',
        ]);
        $query = History::with('user');
        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('from_created_at') && !empty($request->from_created_at)) {
            $query->where('created_at', '>=', $request->from_created_at);
        }
        if ($request->has('to_created_at') && !empty($request->to_created_at)) {
            $query->where('created_at', '<=', $request->to_created_at);
        }
        $histories = $query->get();
        return response()->json(HistoryResource::collection($histories));
    }
}
