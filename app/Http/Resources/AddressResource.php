<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AddressResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = parent::toArray($request);
        $customFields = [
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
        return array_merge($attributes, $customFields);
    }
}
