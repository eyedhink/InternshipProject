<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CategoryResource extends BaseResource
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
            'parent' => CategoryResource::make($this->whenLoaded('parent')),
            'sub_categories' => CategoryResource::collection($this->whenLoaded('subCategories')),
            'products' => ProductResource::collection($this->when(isset($this->all_products), $this->all_products)),
        ];
        return array_merge($attributes, $customFields);
    }
}
