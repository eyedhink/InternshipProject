<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Admin::query()->create([
            'name' => 'Admin',
            'password' => 'password',
        ]);
        User::query()->create([
            'name' => 'User',
            'password' => 'password',
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
