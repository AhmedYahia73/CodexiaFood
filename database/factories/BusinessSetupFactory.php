<?php

namespace Database\Factories;

use App\Models\BusinessSetup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessSetup>
 */
class BusinessSetupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'face' => 'https://facebook.com/'.fake()->userName(),
            'instagram' => 'https://instagram.com/'.fake()->userName(),
            'whats' => fake()->phoneNumber(),
            'logo' => 'business_setup/logo.png',
            'description' => fake()->paragraph(),
            'branch_cover' => 5.00,
            'start_day' => '09:00:00',
            'end_day' => '03:00:00',
        ];
    }
}
