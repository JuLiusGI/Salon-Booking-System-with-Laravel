<?php

namespace Database\Factories;

use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk' => 'public',
            'path' => 'gallery/'.fake()->uuid().'.jpg',
            'title' => fake()->words(2, true),
            'alt_text' => fake()->sentence(4),
            'is_active' => true,
            'display_order' => 0,
        ];
    }
}
