<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RegionSeeder::class,
            IndustrySeeder::class,
            DataSourceSeeder::class,
            DemoStatisticsSeeder::class,
        ]);

        User::updateOrCreate(
            ['email' => 'demo@marketscope.test'],
            [
                'name' => '데모 사용자',
                'password' => 'demo1234',
                'company' => 'MarketScope',
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('데모 계정: demo@marketscope.test / demo1234');
    }
}
