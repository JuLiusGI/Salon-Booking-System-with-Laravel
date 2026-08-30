<?php

namespace Tests\Feature\Database;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_item_snapshot_does_not_change_when_the_service_is_later_edited(): void
    {
        $service = Service::factory()->create([
            'name' => 'Haircut & Blow Dry',
            'price' => 750.00,
            'duration_minutes' => 60,
        ]);

        $appointment = Appointment::factory()->create();
        AppointmentItem::factory()->forService($service)->create([
            'appointment_id' => $appointment->id,
        ]);

        // The salon raises the price and renames the service a month later.
        $service->update([
            'name' => 'Signature Cut & Style',
            'price' => 1200.00,
            'duration_minutes' => 90,
        ]);

        $item = $appointment->fresh()->items->first();

        $this->assertSame('Haircut & Blow Dry', $item->service_name);
        $this->assertSame('750.00', $item->service_price);
        $this->assertSame(60, $item->service_duration_minutes);
    }

    public function test_an_item_survives_its_service_being_deleted(): void
    {
        $service = Service::factory()->create(['name' => 'Discontinued Treatment']);
        $appointment = Appointment::factory()->create();

        AppointmentItem::factory()->forService($service)->create([
            'appointment_id' => $appointment->id,
        ]);

        $service->forceDelete();

        $item = $appointment->fresh()->items->first();

        $this->assertNotNull($item);
        $this->assertNull($item->service_id);
        $this->assertSame('Discontinued Treatment', $item->service_name);
    }

    public function test_the_overlapping_scope_finds_a_conflicting_appointment(): void
    {
        $staff = Staff::factory()->create();
        $start = Carbon::parse('2026-09-15 10:00:00');

        Appointment::factory()->forStaff($staff)->at($start, 60)->create();

        $conflicts = Appointment::query()
            ->forStaff($staff)
            ->blocking()
            ->overlapping(Carbon::parse('2026-09-15 10:30:00'), Carbon::parse('2026-09-15 11:30:00'))
            ->count();

        $this->assertSame(1, $conflicts);
    }

    public function test_back_to_back_appointments_do_not_count_as_overlapping(): void
    {
        $staff = Staff::factory()->create();

        Appointment::factory()->forStaff($staff)
            ->at(Carbon::parse('2026-09-15 10:00:00'), 60)
            ->create();

        $conflicts = Appointment::query()
            ->forStaff($staff)
            ->blocking()
            ->overlapping(Carbon::parse('2026-09-15 11:00:00'), Carbon::parse('2026-09-15 12:00:00'))
            ->count();

        $this->assertSame(0, $conflicts);
    }

    public function test_cancelled_and_no_show_appointments_free_their_slot(): void
    {
        $staff = Staff::factory()->create();
        $start = Carbon::parse('2026-09-15 10:00:00');

        Appointment::factory()->forStaff($staff)->at($start, 60)->cancelled()->create();
        Appointment::factory()->forStaff($staff)->at($start, 60)->noShow()->create();

        $conflicts = Appointment::query()
            ->forStaff($staff)
            ->blocking()
            ->overlapping($start, $start->copy()->addHour())
            ->count();

        $this->assertSame(0, $conflicts);
    }

    public function test_the_development_seeders_produce_a_consistent_dataset(): void
    {
        $this->seed();

        $this->assertDatabaseCount('salon_hours', 7);
        $this->assertDatabaseCount('booking_rules', 1);
        $this->assertGreaterThan(0, Service::query()->count());
        $this->assertGreaterThan(0, Appointment::query()->count());

        // Seeded demo data must not contain a scheduling conflict, otherwise the
        // dataset would contradict the rules the application enforces.
        $overlaps = \DB::table('appointments as a')
            ->join('appointments as b', function ($join) {
                $join->on('a.staff_id', '=', 'b.staff_id')
                    ->whereColumn('a.id', '<', 'b.id')
                    ->whereColumn('a.starts_at', '<', 'b.ends_at')
                    ->whereColumn('a.ends_at', '>', 'b.starts_at');
            })
            ->whereNotIn('a.status', ['cancelled', 'no_show'])
            ->whereNotIn('b.status', ['cancelled', 'no_show'])
            ->count();

        $this->assertSame(0, $overlaps, 'Seeded appointments must never double-book a staff member.');
    }

    public function test_seeded_appointment_totals_reconcile_with_their_items(): void
    {
        $this->seed();

        $mismatched = \DB::table('appointments as a')
            ->join('appointment_items as i', 'i.appointment_id', '=', 'a.id')
            ->groupBy('a.id', 'a.total_duration_minutes', 'a.total_price')
            ->havingRaw('SUM(i.service_duration_minutes) <> a.total_duration_minutes')
            ->orHavingRaw('ROUND(SUM(i.service_price), 2) <> ROUND(a.total_price, 2)')
            ->select('a.id')
            ->get();

        $this->assertCount(0, $mismatched, 'Seeded totals must equal the sum of their items.');
    }
}
