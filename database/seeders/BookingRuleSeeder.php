<?php

namespace Database\Seeders;

use App\Models\BookingRule;
use Illuminate\Database\Seeder;

class BookingRuleSeeder extends Seeder
{
    public function run(): void
    {
        // Single-row configuration table, so seed it only once.
        if (BookingRule::query()->exists()) {
            return;
        }

        BookingRule::create(config('salon.booking_rule_defaults'));
    }
}
