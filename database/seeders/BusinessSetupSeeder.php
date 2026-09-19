<?php

namespace Database\Seeders;

use App\Models\BusinessSetup;
use Illuminate\Database\Seeder;

class BusinessSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! BusinessSetup::exists()) {
            BusinessSetup::create([
                'name' => 'مطعم كودكسا',
                'phone' => '01000000000',
                'face' => 'https://facebook.com/codexarestaurant',
                'instagram' => 'https://instagram.com/codexarestaurant',
                'whats' => '01000000000',
                'logo' => 'business_setup/default_logo.png',
                'description' => 'أشهى المأكولات والمشروبات بأعلى معايير الجودة والخدمة الممتازة.',
            ]);
        }
    }
}
