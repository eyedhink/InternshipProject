<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CartResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $attributes = parent::toArray($request);
        $customFields = [
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
        return array_merge($attributes, $customFields);
    }
}
