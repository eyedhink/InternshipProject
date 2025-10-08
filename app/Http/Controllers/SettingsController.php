<?php

namespace App\Http\Controllers;

use App\Http\Resources\SettingsResource;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function update_contact_us(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'instagram' => ['sometimes', 'string'],
            'email' => ['sometimes', 'string', 'email'],
            'phone_number' => ['sometimes', 'string'],
            'address' => ['sometimes', 'string'],
        ]);
        if ($request->has('instagram')) {
            Settings::query()->firstWhere('key', 'contact_us_instagram')->update(['value' => $validated['instagram']]);
        }
        if ($request->has('email')) {
            Settings::query()->firstWhere('key', 'contact_us_email')->update(['value' => $validated['email']]);
        }
        if ($request->has('phone_number')) {
            Settings::query()->firstWhere('key', 'contact_us_phone_number')->update(['value' => $validated['phone_number']]);
        }
        if ($request->has('address')) {
            Settings::query()->firstWhere('key', 'contact_us_address')->update(['value' => $validated['address']]);
        }
        return response()->json(['message' => 'Contact us updated successfully']);
    }

    public function get_contact_us(): JsonResponse
    {
        return response()->json(SettingsResource::collection(Settings::query()->where('key', "LIKE", 'contact_us%')->get()));
    }

    public function update_about_us(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['sometimes', 'string'],
            'image' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp'],
        ]);
        if ($request->has('text')) {
            Settings::query()->updateOrCreate(
                ['key' => 'about_us_text'],
                ['value' => $validated['text']]
            );
        }
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('about_us', 'public');
            $aboutUsImageSetting = Settings::query()->firstWhere('key', 'about_us_image');

            if ($aboutUsImageSetting) {
                if (Storage::disk('public')->exists($aboutUsImageSetting->value)) {
                    Storage::disk('public')->delete($aboutUsImageSetting->value);
                }
                $aboutUsImageSetting->update(['value' => $imagePath]);
            } else {
                Settings::query()->create([
                    'key' => 'about_us_image',
                    'value' => $imagePath
                ]);
            }
        }

        return response()->json(['message' => 'About us updated successfully']);
    }

    public function get_about_us(): JsonResponse
    {
        return response()->json(SettingsResource::collection(Settings::query()->where('key', "LIKE", 'about_us%')->get()));
    }

    public function sub_info(): JsonResponse
    {
        $contact_us = SettingsResource::collection(Settings::query()->where('key', "LIKE", 'contact_us%')->get());
        return response()->json(['contact_us' => $contact_us]);
    }
}
