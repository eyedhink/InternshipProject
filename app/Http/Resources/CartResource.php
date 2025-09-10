<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CartResource extends BaseResource
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
            'items' => ItemResource::collection($this->whenLoaded('items')),
            'address' => AddressResource::make($this->whenLoaded('address')),
            'discount' => DiscountResource::make($this->whenLoaded('discount')),
            'discount_admin' => DiscountResource::make($this->whenLoaded('discount_admin')),
        ];
        return array_merge($attributes, $customFields);
    }
}
