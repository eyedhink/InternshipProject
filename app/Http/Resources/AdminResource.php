<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = parent::toArray($request);
        $customFields = [];
        return array_merge($attributes, $customFields);
    }
}
