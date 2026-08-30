<?php

namespace Tests\Feature\Database;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_has_one_customer_profile(): void
    {
        $user = User::factory()->has(CustomerProfile::factory(), 'customerProfile')->create();

        $this->assertInstanceOf(CustomerProfile::class, $user->customerProfile);
        $this->assertTrue($user->customerProfile->user->is($user));
    }

    public function test_a_staff_member_belongs_to_a_user(): void
    {
        $staff = Staff::factory()->create();

        $this->assertInstanceOf(User::class, $staff->user);
        $this->assertSame(UserRole::Stylist, $staff->user->role);
        $this->assertTrue($staff->user->staff->is($staff));
    }

    public function test_a_service_belongs_to_a_category_and_a_category_has_many_services(): void
    {
        $category = ServiceCategory::factory()->create();
        $services = Service::factory()->count(3)->create(['service_category_id' => $category->id]);

        $this->assertCount(3, $category->refresh()->services);
        $this->assertTrue($services->first()->category->is($category));
    }

    public function test_services_and_staff_are_related_many_to_many(): void
    {
        $service = Service::factory()->create();
        $staff = Staff::factory()->count(2)->create();

        $service->staff()->attach($staff->pluck('id'));

        $this->assertCount(2, $service->refresh()->staff);
        $this->assertTrue($staff->first()->refresh()->services->first()->is($service));
    }

    public function test_can_perform_reflects_the_service_assignment(): void
    {
        $service = Service::factory()->create();
        $assigned = Staff::factory()->create();
        $unassigned = Staff::factory()->create();

        $assigned->services()->attach($service);

        $this->assertTrue($assigned->canPerform($service));
        $this->assertFalse($unassigned->canPerform($service));
    }

    public function test_an_appointment_relates_to_its_customer_staff_and_items(): void
    {
        $customer = User::factory()->create();
        $staff = Staff::factory()->create();

        $appointment = Appointment::factory()
            ->forCustomer($customer)
            ->forStaff($staff)
            ->create();

        AppointmentItem::factory()->count(2)->create(['appointment_id' => $appointment->id]);

        $appointment->refresh();

        $this->assertTrue($appointment->customer->is($customer));
        $this->assertTrue($appointment->staff->is($staff));
        $this->assertCount(2, $appointment->items);
        $this->assertTrue($customer->appointments->first()->is($appointment));
    }

    public function test_appointment_items_are_ordered_by_position(): void
    {
        $appointment = Appointment::factory()->create();

        AppointmentItem::factory()->create(['appointment_id' => $appointment->id, 'position' => 2]);
        AppointmentItem::factory()->create(['appointment_id' => $appointment->id, 'position' => 0]);
        AppointmentItem::factory()->create(['appointment_id' => $appointment->id, 'position' => 1]);

        $this->assertSame([0, 1, 2], $appointment->items->pluck('position')->all());
    }

    public function test_status_and_role_are_cast_to_enums(): void
    {
        $appointment = Appointment::factory()->pending()->create();
        $admin = User::factory()->admin()->create();

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isStaffMember());
    }

    public function test_money_and_datetimes_round_trip_with_the_right_types(): void
    {
        $service = Service::factory()->create(['price' => 1234.56, 'duration_minutes' => 90]);

        $fresh = $service->fresh();

        $this->assertSame('1234.56', $fresh->price);
        $this->assertSame(90, $fresh->duration_minutes);

        $appointment = Appointment::factory()->create();
        $this->assertInstanceOf(Carbon::class, $appointment->fresh()->starts_at);
    }

    public function test_a_rescheduled_appointment_links_back_to_the_original(): void
    {
        $original = Appointment::factory()->create();
        $replacement = Appointment::factory()->create(['rescheduled_from_id' => $original->id]);

        $this->assertTrue($replacement->fresh()->rescheduledFrom->is($original));
    }
}
