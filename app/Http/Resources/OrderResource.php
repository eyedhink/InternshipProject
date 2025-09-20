<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class OrderResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $attributes = parent::toArray($request);

        if (isset($attributes['info']) && is_array($attributes['info'])) {
            $attributes = array_merge($attributes, $attributes['info']);
            unset($attributes['info']);
        }

        $customFields = [
            "date" => $this->date_shamsi
        ];

        return array_merge($attributes, $customFields);
    }
}
