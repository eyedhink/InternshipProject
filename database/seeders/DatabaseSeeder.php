<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Settings;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Admin::query()->create([
            'name' => 'Admin',
            'password' => Hash::make('password'),
        ]);
        User::query()->create([
            'name' => 'User',
            'password' => Hash::make('password'),
            'email' => 'user@user.com',
            'phone_number' => '0123456789',
            'email_verified_at' => now(),
            'wallet_balance' => 10000,
        ]);
        Category::query()->create([
            "title" => 'Food',
            "parent_id" => Null,
        ]);
        Category::query()->create([
            "title" => 'Sports',
            "parent_id" => Null,
        ]);
        Category::query()->create([
            "title" => 'Chips',
            "parent_id" => 1,
        ]);
        Category::query()->create([
            "title" => 'Balls',
            "parent_id" => 2,
        ]);
        Product::query()->create([
            "title" => "Chips",
            "description" => "Very Crispy",
            "features" => [
                "Spicy",
                "Sauce Included"
            ],
            "image_1" => "products/placeholder.png",
            "image_2" => "products/placeholder.png",
            "image_3" => "products/placeholder.png",
            "subcategory_id" => 3,
            "show_in_home_page" => true,
            "stock" => rand(0, 1000),
            "before_discount_price" => rand(100, 1000),
            "discount_percentage" => rand(0, 100),
            "sold_count" => rand(0, 1000),
        ]);
        Product::query()->create([
            "title" => "Fish n Chips",
            "description" => "Chips with a side of fish",
            "features" => [
                "Fish",
                "Seafood",
                "Slippery"
            ],
            "image_1" => "placeholder.png",
            "image_2" => "placeholder.png",
            "image_3" => "placeholder.png",
            "subcategory_id" => 3,
            "show_in_home_page" => true,
            "stock" => rand(0, 1000),
            "before_discount_price" => rand(100, 1000),
            "discount_percentage" => rand(0, 100),
            "sold_count" => rand(0, 1000),
        ]);
        Product::query()->create([
            "title" => "Football",
            "description" => "Touched by Ronaldo",
            "features" => [
                "Round",
                "Touched By Celebrity"
            ],
            "image_1" => "placeholder.png",
            "image_2" => "placeholder.png",
            "image_3" => "placeholder.png",
            "subcategory_id" => 4,
            "show_in_home_page" => true,
            "stock" => rand(0, 1000),
            "before_discount_price" => rand(100, 1000),
            "discount_percentage" => rand(0, 100),
            "sold_count" => rand(0, 1000),
        ]);
        Address::query()->create([
            "user_id" => 1,
            "description" => "Idk",
            "province" => "Mazandaran",
            "city" => "Qaemshahr"
        ]);
        Discount::query()->create([
            'code' => "dis",
            'discount_percentage' => rand(0, 100),
            'max_amount' => rand(100000, 9999999999),
            'expires_at' => now()->addDays(30),
        ]);
        Cart::query()->create([
            'user_id' => 1,
            'address_id' => 1,
            'discount_id' => 1,
        ]);
        Settings::query()->create([
            'key' => "contact_us_instagram",
            'value' => "@John"
        ]);
        Settings::query()->create([
            'key' => "contact_us_email",
            'value' => "john@example.com"
        ]);
        Settings::query()->create([
            'key' => "contact_us_phone_number",
            'value' => "+123456789"
        ]);
        Settings::query()->create([
            'key' => "contact_us_address",
            'value' => "Madrid"
        ]);
        Settings::query()->create([
            'key' => "about_us_text",
            'value' => "About us"
        ]);
        Settings::query()->create([
            'key' => "about_us_image",
            'value' => "about_us/placeholder.png"
        ]);
        for ($i = 0; $i < 2; ++$i) {
            Slide::query()->create([
                'image_url' => "slides/placeholder.png",
                'title' => "sth",
                'subtitle' => "sth",
                'link' => "#",
            ]);
        }
    }
}
