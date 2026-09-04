<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Delivery;
use Illuminate\Database\Seeder;

class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        $drivers = [
            ['name' => 'طيار محمود فتحي', 'phone' => '01011112222'],
            ['name' => 'طيار إسلام السيد', 'phone' => '01122223333'],
            ['name' => 'طيار خالد صبري', 'phone' => '01233334444'],
            ['name' => 'طيار عمرو عادل', 'phone' => '01544445555'],
        ];

        foreach ($branches as $branch) {
            foreach ($drivers as $index => $driver) {
                Delivery::create([
                    'name' => "{$driver['name']} ({$branch->name})",
                    'phone' => $driver['phone'],
                    'id_images' => [
                        'uploads/deliveries/national_id_front.jpg',
                        'uploads/deliveries/national_id_back.jpg',
                        'uploads/deliveries/license.jpg',
                    ],
                    'branch_id' => $branch->id,
                ]);
            }
        }
    }
}
