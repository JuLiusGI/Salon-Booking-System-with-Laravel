<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceCatalogSeeder extends Seeder
{
    /**
     * Demo catalogue: category => [name, duration minutes, price].
     *
     * @var array<string, list<array{0: string, 1: int, 2: float}>>
     */
    private const CATALOG = [
        'Hair' => [
            ['Haircut & Blow Dry', 60, 750.00],
            ['Hair Colour', 120, 2800.00],
            ['Highlights', 150, 3800.00],
            ['Keratin Treatment', 180, 4500.00],
            ['Hair Spa', 60, 1200.00],
        ],
        'Nails' => [
            ['Classic Manicure', 45, 450.00],
            ['Gel Manicure', 60, 850.00],
            ['Classic Pedicure', 60, 550.00],
            ['Nail Art Add-on', 30, 350.00],
        ],
        'Skin & Facial' => [
            ['Deep Cleansing Facial', 75, 1500.00],
            ['Hydrating Facial', 60, 1800.00],
            ['Diamond Peel', 45, 1650.00],
        ],
        'Spa & Massage' => [
            ['Swedish Massage', 90, 1400.00],
            ['Hot Stone Massage', 90, 1900.00],
            ['Foot Reflexology', 45, 700.00],
        ],
        'Makeup' => [
            ['Everyday Makeup', 60, 1500.00],
            ['Event Makeup', 90, 2800.00],
        ],
    ];

    public function run(): void
    {
        $bookableStaff = Staff::query()->bookable()->orderBy('display_order')->get();

        $categoryOrder = 0;

        foreach (self::CATALOG as $categoryName => $services) {
            $category = ServiceCategory::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'description' => "Our {$categoryName} services.",
                'is_active' => true,
                'display_order' => $categoryOrder++,
            ]);

            foreach ($services as $order => [$name, $duration, $price]) {
                $service = Service::create([
                    'service_category_id' => $category->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => fake()->sentence(12),
                    'duration_minutes' => $duration,
                    'price' => $price,
                    'is_active' => true,
                    'display_order' => $order,
                ]);

                // Assign a realistic subset of staff to each service so that
                // service/staff compatibility is actually meaningful in dev data.
                if ($bookableStaff->isNotEmpty()) {
                    $assigned = $bookableStaff->random(min(2, $bookableStaff->count()));
                    $service->staff()->attach($assigned->pluck('id'));
                }
            }
        }
    }
}
