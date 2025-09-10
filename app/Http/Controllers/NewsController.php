<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    public function update(Request $request, string $id)
    {
        $news = News::query()->findOrFail($id);
        $validated = $request->validate([
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg',
            'title' => 'sometimes|string',
            'text' => 'sometimes|string',
            'is_important' => 'boolean',
        ]);
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('news', 'public');
        }
        if (isset($validated['image'])) {
            if ($news->image && Storage::disk('public')->exists($news->image)) {
                Storage::disk('public')->delete($news->image);
            }
        }
        $news->update($validated);
        return response()->json(NewsResource::make($news));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg',
            'title' => 'required|string',
            'text' => 'required|string',
            'is_important' => 'boolean',
        ]);
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('news', 'public');
        }
        $news = News::query()->create($validated);
        return response()->json(NewsResource::make($news));
    }

    public function get_by_id(string $id)
    {
        $news = News::query()->findOrFail($id);
        return response()->json(NewsResource::make($news));
    }

    public function get()
    {
        $validated = request()->validate([
            'is_important' => 'sometimes|boolean',
        ]);
        $query = News::query();
        if (isset($validated['is_important'])) {
            $query->where('is_important', $validated['is_important']);
        }
        $news = $query->get();
        return response()->json(NewsResource::collection($news));
    }
}
