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
    }
}
