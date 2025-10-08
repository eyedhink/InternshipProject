<?php

namespace App\Http\Controllers;

use App\Http\Resources\SlideResource;
use App\Models\Slide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SlideController extends Controller
{
    public function update(string $id, Request $request): JsonResponse
    {
        $slide = Slide::query()->findOrFail($id);

        $validated = $request->validate([
            'image_url' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp'],
            'title' => ['sometimes', 'string', 'nullable'],
            'subtitle' => ['sometimes', 'string', 'nullable'],
            'link' => ['sometimes', 'string', 'nullable'],
            'order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('image_url')) {
            if ($slide->image_url && Storage::disk('public')->exists($slide->image_url)) {
                Storage::disk('public')->delete($slide->image_url);
            }

            $imagePath = $request->file('image_url')->store('slides', 'public');
            $validated['image_url'] = $imagePath;
        }

        $slide->update($validated);

        return response()->json([
            'data' => SlideResource::make($slide),
            'image_url' => $slide->image_url ? Storage::url($slide->image_url) : null
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_url' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'title' => ['sometimes', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'string', 'max:255'],
            'link' => ['sometimes', 'string', 'max:255'],
            'order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('image_url')) {
            $validated['image_url'] = $request->file('image_url')->store('slides', 'public');
        }

        $slide = Slide::query()->create($validated);

        return response()->json(SlideResource::make($slide));
    }

    public function destroy(string $id): JsonResponse
    {
        $slide = Slide::query()->findOrFail($id);

        if ($slide->image_url && Storage::disk('public')->exists($slide->image_url)) {
            Storage::disk('public')->delete($slide->image_url);
        }

        $slide->delete();

        return response()->json(["message" => "Slide has been deleted"]);
    }

    public function getById(string $id): JsonResponse
    {
        $slide = Slide::query()->findOrFail($id);
        return response()->json(SlideResource::make($slide));
    }

    public function getActive(): JsonResponse
    {
        $slides = Slide::query()->where('is_active', true)
            ->orderBy('order')
            ->get();

        return response()->json(SlideResource::collection($slides));
    }

    public function get(): JsonResponse
    {
        $slides = Slide::query()->orderBy('order')->get();
        return response()->json(SlideResource::collection($slides));
    }
}
