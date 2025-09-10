<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SlideResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $attributes = parent::toArray($request);
        $customFields = [];
        return array_merge($attributes, $customFields);
    }
}
