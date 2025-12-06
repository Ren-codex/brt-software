<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListBrand>
 */
class ListBrandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $riceBrands = [
            'Golden Harvest',
            'Premium Rice Co.',
            'RiceMaster',
            'Pure Grain',
            'Elite Rice',
            'Superior Rice',
            'Rice Delight',
            'Harvest King',
            'Rice Paradise',
            'Grain Masters',
            'Rice Supreme',
            'Golden Grain',
            'Rice Essence',
            'Harvest Gold',
            'Rice Crown',
            'Grain Elite',
            'Rice Noble',
            'Harvest Prime',
            'Rice Royal',
            'Grain Prestige'
        ];

        return [
            'name' => fake()->randomElement($riceBrands),
        ];
    }
}
