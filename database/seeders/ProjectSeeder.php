<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            ['name' => 'Billing',     'sort_order' => 1, 'is_active' => true],
            ['name' => 'Delivery',    'sort_order' => 2, 'is_active' => true],
            ['name' => 'Mobile App',  'sort_order' => 3, 'is_active' => true],
            ['name' => 'Website',     'sort_order' => 4, 'is_active' => true],
            // "Other" can be disabled via is_active=false in DB without code changes
            ['name' => 'Other',       'sort_order' => 5, 'is_active' => true],
        ];

        foreach ($projects as $project) {
            DB::table('projects')->updateOrInsert(
                ['name' => $project['name']],
                array_merge($project, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
