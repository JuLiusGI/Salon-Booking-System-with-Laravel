<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Staff members seeded for development.
     *
     * All names and contact details here are invented for demo purposes and must
     * never be replaced with real personal data (MASTER_SPEC section 26).
     *
     * @var list<array{name: string, email: string, title: string, bookable: bool, role: UserRole}>
     */
    private const STAFF = [
        ['name' => 'Marisol Reyes', 'email' => 'marisol@salon.test', 'title' => 'Senior Stylist', 'bookable' => true, 'role' => UserRole::Stylist],
        ['name' => 'Dahlia Cruz', 'email' => 'dahlia@salon.test', 'title' => 'Colour Specialist', 'bookable' => true, 'role' => UserRole::Stylist],
        ['name' => 'Beatriz Santos', 'email' => 'beatriz@salon.test', 'title' => 'Nail Technician', 'bookable' => true, 'role' => UserRole::Stylist],
        ['name' => 'Ingrid Villanueva', 'email' => 'ingrid@salon.test', 'title' => 'Aesthetician', 'bookable' => true, 'role' => UserRole::Stylist],
        ['name' => 'Corazon Aquino-Lim', 'email' => 'front.desk@salon.test', 'title' => 'Receptionist', 'bookable' => false, 'role' => UserRole::Receptionist],
    ];

    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Salon Administrator',
            'email' => 'admin@salon.test',
        ]);

        foreach (self::STAFF as $index => $member) {
            $user = User::factory()->role($member['role'])->create([
                'name' => $member['name'],
                'email' => $member['email'],
            ]);

            Staff::factory()->create([
                'user_id' => $user->id,
                'title' => $member['title'],
                'is_bookable' => $member['bookable'],
                'display_order' => $index,
            ]);
        }

        // A predictable customer account for manual testing, plus a realistic
        // spread of additional customers.
        $demo = User::factory()->create([
            'name' => 'Demo Customer',
            'email' => 'customer@salon.test',
        ]);
        CustomerProfile::factory()->create(['user_id' => $demo->id]);

        User::factory()
            ->count(24)
            ->has(CustomerProfile::factory(), 'customerProfile')
            ->create();
    }
}
