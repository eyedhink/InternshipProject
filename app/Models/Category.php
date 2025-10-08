<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'title',
        'parent_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function subCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'subcategory_id');
    }

    public function getAllProductsAttribute(): Collection
    {
        $categoryIds = $this->getAllDescendantIds($this->id);
        $categoryIds[] = $this->id;

        return Product::query()->whereIn('subcategory_id', $categoryIds)->get();
    }

    protected function getAllDescendantIds($categoryId): array
    {
        if (config('database.default') === 'mysql' || config('database.default') === 'pgsql') {
            return $this->getDescendantIdsWithCte($categoryId);
        }

        return $this->getDescendantIdsWithLoop($categoryId);
    }

    protected function getDescendantIdsWithCte($categoryId): array
    {
        $query = DB::select("
            WITH RECURSIVE category_tree AS (
                SELECT id, parent_id
                FROM categories
                WHERE parent_id = :categoryId
                UNION ALL
                SELECT c.id, c.parent_id
                FROM categories c
                INNER JOIN category_tree ct ON c.parent_id = ct.id
            )
            SELECT id FROM category_tree
        ", ['categoryId' => $categoryId]);

        return array_column($query, 'id');
    }

    protected function getDescendantIdsWithLoop($categoryId): array
    {
        $descendantIds = [];
        $currentLevelIds = [$categoryId];

        do {
            $nextLevelIds = Category::query()->whereIn('parent_id', $currentLevelIds)
                ->pluck('id')
                ->toArray();

            $descendantIds = array_merge($descendantIds, $nextLevelIds);
            $currentLevelIds = $nextLevelIds;
        } while (!empty($currentLevelIds));

        return $descendantIds;
    }
}
