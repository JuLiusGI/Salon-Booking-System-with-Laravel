<?php

namespace Database\Seeders;

use App\Models\GalleryImage;
use Illuminate\Database\Seeder;

class GallerySeeder extends Seeder
{
    public function run(): void
    {
        // Placeholder records only. Real image files are uploaded through the
        // admin interface; these rows exist so gallery listings have data.
        $titles = [
            'Salon interior', 'Styling stations', 'Colour bar',
            'Manicure lounge', 'Treatment room', 'Reception',
        ];

        foreach ($titles as $order => $title) {
            GalleryImage::create([
                'disk' => 'public',
                'path' => 'gallery/placeholder-'.($order + 1).'.jpg',
                'title' => $title,
                'alt_text' => $title.' at the salon',
                'is_active' => true,
                'display_order' => $order,
            ]);
        }
    }
}
