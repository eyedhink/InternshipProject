<?php

namespace App\Models;

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

    // Efficient method to get all products from this category and all subcategories
    public function getAllProductsAttribute()
    {
        // Get all descendant category IDs using a single query
        $categoryIds = $this->getAllDescendantIds($this->id);
        $categoryIds[] = $this->id; // Include the current category

        // Return products from all these categories
        return Product::whereIn('subcategory_id', $categoryIds)->get();
    }

    // Efficient method to get all descendant category IDs
    protected function getAllDescendantIds($categoryId): array
    {
        // Use a recursive CTE if your database supports it (MySQL 8.0+, PostgresSQL, SQL Server)
        if (config('database.default') === 'mysql' || config('database.default') === 'pgsql') {
            return $this->getDescendantIdsWithCte($categoryId);
        }

        // Fallback for databases that don't support CTEs (like older MySQL versions)
        return $this->getDescendantIdsWithLoop($categoryId);
    }

    // Using CTE for databases that support it (more efficient)
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

    // Fallback method for databases that don't support CTEs
    protected function getDescendantIdsWithLoop($categoryId): array
    {
        $descendantIds = [];
        $currentLevelIds = [$categoryId];

        do {
            $nextLevelIds = Category::whereIn('parent_id', $currentLevelIds)
                ->pluck('id')
                ->toArray();

            $descendantIds = array_merge($descendantIds, $nextLevelIds);
            $currentLevelIds = $nextLevelIds;
        } while (!empty($currentLevelIds));

        return $descendantIds;
    }
}
