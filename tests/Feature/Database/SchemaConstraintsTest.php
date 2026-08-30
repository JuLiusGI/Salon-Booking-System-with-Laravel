<?php

namespace Tests\Feature\Database;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\SalonHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_emails_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@salon.test']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'taken@salon.test']);
    }

    public function test_service_slugs_must_be_unique(): void
    {
        Service::factory()->create(['slug' => 'haircut']);

        $this->expectException(QueryException::class);
        Service::factory()->create(['slug' => 'haircut']);
    }

    public function test_a_staff_member_cannot_be_assigned_to_the_same_service_twice(): void
    {
        $service = Service::factory()->create();
        $staff = Staff::factory()->create();

        $service->staff()->attach($staff);

        $this->expectException(QueryException::class);
        $service->staff()->attach($staff);
    }

    public function test_each_weekday_has_at_most_one_salon_hours_row(): void
    {
        SalonHour::factory()->create(['day_of_week' => 3]);

        $this->expectException(QueryException::class);
        SalonHour::factory()->create(['day_of_week' => 3]);
    }

    public function test_appointment_references_must_be_unique(): void
    {
        Appointment::factory()->create(['reference' => 'SB-DUPLICATE']);

        $this->expectException(QueryException::class);
        Appointment::factory()->create(['reference' => 'SB-DUPLICATE']);
    }

    public function test_a_category_holding_services_cannot_be_hard_deleted(): void
    {
        $category = ServiceCategory::factory()->create();
        Service::factory()->create(['service_category_id' => $category->id]);

        $this->expectException(QueryException::class);
        $category->forceDelete();
    }

    public function test_deleting_an_appointment_removes_its_items(): void
    {
        $appointment = Appointment::factory()->create();
        AppointmentItem::factory()->count(2)->create(['appointment_id' => $appointment->id]);

        $appointment->forceDelete();

        $this->assertDatabaseCount('appointment_items', 0);
    }

    public function test_deleting_a_user_removes_their_customer_profile(): void
    {
        $user = User::factory()->create();
        $user->customerProfile()->create([]);

        $user->forceDelete();

        $this->assertDatabaseCount('customer_profiles', 0);
    }

    public function test_soft_deleted_records_are_hidden_but_retained(): void
    {
        $service = Service::factory()->create();

        $service->delete();

        $this->assertNull(Service::find($service->id));
        $this->assertNotNull(Service::withTrashed()->find($service->id));
        $this->assertDatabaseCount('services', 1);
    }

    public function test_role_cannot_be_set_by_mass_assignment(): void
    {
        $user = new User;
        $user->fill(['name' => 'X', 'email' => 'x@salon.test', 'password' => 'secret', 'role' => UserRole::Admin]);

        $this->assertNotSame(UserRole::Admin, $user->role);
    }

    public function test_appointment_status_cannot_be_set_by_mass_assignment(): void
    {
        $appointment = new Appointment;
        $appointment->fill(['status' => AppointmentStatus::Completed]);

        $this->assertNotSame(AppointmentStatus::Completed, $appointment->status);
    }

    public function test_the_qr_token_is_hidden_from_serialization(): void
    {
        $appointment = Appointment::factory()->create();

        $this->assertArrayNotHasKey('qr_token', $appointment->toArray());
        $this->assertNotNull($appointment->qr_token);
    }
}
