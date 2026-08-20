<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BotLocaleSeeder extends Seeder
{
    public function run(): void
    {
        $locales = [
            ['code' => 'uk', 'name' => 'Українська', 'is_default' => true],
            ['code' => 'en', 'name' => 'English', 'is_default' => false],
            ['code' => 'ru', 'name' => 'Русский', 'is_default' => false],
        ];

        foreach ($locales as $locale) {
            DB::table('bot_locales')->updateOrInsert(
                ['code' => $locale['code']],
                array_merge($locale, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
