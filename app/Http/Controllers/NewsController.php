<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    public function update(Request $request, string $id): JsonResponse
    {
        $news = News::query()->findOrFail($id);
        $validated = $request->validate([
            'image' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp'],
            'image_alt' => ['sometimes', 'string'],
            'title' => ['sometimes', 'string'],
            'text' => ['sometimes', 'string'],
            'is_important' => ['sometimes', 'boolean'],
        ]);
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $validated['image'] = $image->store('news', 'public');
            $path = $image->getPathname();
            list($width, $height) = getimagesize($path);
            $size = $width * $height;
        }
        if (isset($validated['image'])) {
            if ($news->image && Storage::disk('public')->exists($news->image['url'])) {
                Storage::disk('public')->delete($news->image['url']);
            }
        }
        $news_info = [];
        foreach ($validated as $key => $value) {
            if ($key == 'image') {
                $news_info['image'] = [
                    "url" => $value,
                    "width" => $width ?? null,
                    "height" => $height ?? null,
                    "size" => $size ?? null,
                ];
            } else if ($key == 'image_alt') {
                $news_info['image']['alt'] = $value;
            } else {
                $news_info[$key] = $value;
            }
        }
        $news->update($news_info);
        return response()->json(NewsResource::make($news));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp'],
            'image_alt' => ['sometimes', 'string'],
            'title' => ['required', 'string'],
            'text' => ['required', 'string'],
            'is_important' => ['boolean'],
        ]);
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $validated['image'] = $image->store('news', 'public');
            $path = $image->getPathname();
            list($width, $height) = getimagesize($path);
            $size = $width * $height;
        }
        $news_info = [
            'title' => $validated['title'],
            'text' => $validated['text'],
            'is_important' => $validated['is_important'],
            'image' => [
                'url' => $validated['image'],
                'width' => $width ?? null,
                "height" => $height ?? null,
                "size" => $size ?? null,
                "alt" => $validated['image_alt'],
            ],
        ];
        $news = News::query()->create($news_info);
        return response()->json(NewsResource::make($news));
    }

    public function destroy(string $id): JsonResponse
    {
        $news = News::query()->findOrFail($id);
        Storage::disk('public')->delete($news->image['url']);
        $news->delete();
        return response()->json(["message" => "News has been deleted successfully"]);
    }

    public function get_by_id(string $id): JsonResponse
    {
        $news = News::query()->findOrFail($id);
        return response()->json(NewsResource::make($news));
    }

    public function get(): JsonResponse
    {
        $validated = request()->validate([
            'is_important' => ['sometimes', 'boolean'],
            'page' => ['required', 'integer', 'min:1'],
            'limit' => ["sometimes", "integer", "min:1"],
        ]);
        $query = News::query();
        if (isset($validated['is_important'])) {
            $query->where('is_important', $validated['is_important']);
        }
        $news = $query->get();
        $limit = $validated['limit'] ?? 10;
        $news = $query->paginate($limit, page: $validated['page']);
        $total_pages = ceil($news->total() / $limit);
        $pagination_info = [
            "total_items" => $news->total(),
            "total_pages" => $total_pages,
            "current_page" => $validated["page"],
            "per_page" => $limit,
            "has_next_page" => ($total_pages - $validated['page']) > 0,
            "has_previous_page" => ($validated['page'] - 1) > 0,
            "next_page" => $validated['page'] + 1,
            "previous_page" => $validated['page'] - 1
        ];
        return response()->json(["data" => NewsResource::collection($news), "pagination_info" => $pagination_info]);
    }
}
