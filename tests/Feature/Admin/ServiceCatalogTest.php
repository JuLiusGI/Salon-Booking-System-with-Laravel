<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function servicePayload(array $overrides = []): array
    {
        return array_merge([
            'service_category_id' => ServiceCategory::factory()->create()->id,
            'name' => 'Balayage',
            'description' => 'Hand painted highlights.',
            'duration_minutes' => 150,
            'price' => '3800.00',
            'is_active' => true,
            'display_order' => 0,
        ], $overrides);
    }

    /* Categories ---------------------------------------------------------- */

    public function test_an_admin_can_create_a_category_with_a_generated_slug(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/categories', [
                'name' => 'Hair & Colour',
                'description' => 'Cuts and colour work.',
                'is_active' => true,
                'display_order' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('service_categories', [
            'name' => 'Hair & Colour',
            'slug' => 'hair-colour',
        ]);
    }

    public function test_duplicate_slugs_are_made_unique_rather_than_failing(): void
    {
        ServiceCategory::factory()->create(['name' => 'Existing', 'slug' => 'hair']);

        $this->actingAs($this->admin())->post('/admin/categories', [
            'name' => 'Hair',
            'is_active' => true,
            'display_order' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('service_categories', ['name' => 'Hair', 'slug' => 'hair-2']);
    }

    public function test_a_category_name_cannot_be_duplicated(): void
    {
        ServiceCategory::factory()->create(['name' => 'Hair']);

        $this->actingAs($this->admin())
            ->post('/admin/categories', ['name' => 'Hair', 'is_active' => true, 'display_order' => 0])
            ->assertSessionHasErrors('name');
    }

    public function test_a_category_holding_services_cannot_be_deleted(): void
    {
        $category = ServiceCategory::factory()->create();
        Service::factory()->create(['service_category_id' => $category->id]);

        $this->actingAs($this->admin())
            ->delete("/admin/categories/{$category->id}")
            ->assertSessionHasErrors('category');

        $this->assertNotSoftDeleted($category);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $category = ServiceCategory::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/admin/categories/{$category->id}")
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($category);
    }

    /* Services ------------------------------------------------------------ */

    public function test_an_admin_can_create_a_service(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/services', $this->servicePayload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('services', [
            'name' => 'Balayage',
            'slug' => 'balayage',
            'duration_minutes' => 150,
        ]);
    }

    public function test_a_service_can_be_assigned_to_bookable_staff(): void
    {
        $staff = Staff::factory()->count(2)->create();

        $this->actingAs($this->admin())
            ->post('/admin/services', $this->servicePayload(['staff_ids' => $staff->pluck('id')->all()]))
            ->assertSessionHasNoErrors();

        $service = Service::where('name', 'Balayage')->firstOrFail();

        $this->assertCount(2, $service->staff);
    }

    public function test_a_service_cannot_be_assigned_to_a_staff_member_who_is_not_bookable(): void
    {
        $receptionist = Staff::factory()->notBookable()->create();

        $this->actingAs($this->admin())
            ->post('/admin/services', $this->servicePayload(['staff_ids' => [$receptionist->id]]))
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('services', ['name' => 'Balayage']);
    }

    public function test_a_service_cannot_be_assigned_to_an_inactive_staff_member(): void
    {
        $inactive = Staff::factory()->inactive()->create();

        $this->actingAs($this->admin())
            ->post('/admin/services', $this->servicePayload(['staff_ids' => [$inactive->id]]))
            ->assertSessionHasErrors();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidServiceValues(): array
    {
        return [
            'zero duration' => [['duration_minutes' => 0], 'duration_minutes'],
            'negative duration' => [['duration_minutes' => -30], 'duration_minutes'],
            'absurd duration' => [['duration_minutes' => 5000], 'duration_minutes'],
            'negative price' => [['price' => -100], 'price'],
            'non numeric price' => [['price' => 'free'], 'price'],
            'missing name' => [['name' => ''], 'name'],
            'unknown category' => [['service_category_id' => 999999], 'service_category_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidServiceValues')]
    public function test_invalid_service_values_are_rejected(array $overrides, string $field): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/services', $this->servicePayload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseMissing('services', ['name' => 'Balayage']);
    }

    public function test_editing_a_service_does_not_rewrite_past_appointments(): void
    {
        $service = Service::factory()->create(['name' => 'Cut', 'price' => 500, 'duration_minutes' => 45]);

        $appointment = Appointment::factory()->create();
        AppointmentItem::factory()->forService($service)->create([
            'appointment_id' => $appointment->id,
        ]);

        $this->actingAs($this->admin())->patch("/admin/services/{$service->id}", $this->servicePayload([
            'service_category_id' => $service->service_category_id,
            'name' => 'Cut and Finish',
            'price' => '900.00',
            'duration_minutes' => 75,
        ]))->assertSessionHasNoErrors();

        $item = $appointment->fresh()->items->first();

        $this->assertSame('Cut', $item->service_name);
        $this->assertSame('500.00', $item->service_price);
        $this->assertSame(45, $item->service_duration_minutes);
    }

    public function test_deleting_a_service_only_soft_deletes_it(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/admin/services/{$service->id}")
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($service);
        $this->assertDatabaseCount('services', 1);
    }

    /* Images -------------------------------------------------------------- */

    public function test_an_uploaded_image_is_stored_under_a_generated_name(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post('/admin/services', $this->servicePayload([
            'image' => UploadedFile::fake()->image('my holiday photo.jpg', 800, 600),
        ]))->assertSessionHasNoErrors();

        $service = Service::where('name', 'Balayage')->firstOrFail();

        $this->assertNotNull($service->image_path);

        // The original filename is never trusted or reused.
        $this->assertStringNotContainsString('my holiday photo', $service->image_path);
        $this->assertStringStartsWith('services/', $service->image_path);
        Storage::disk('public')->assertExists($service->image_path);
    }

    /**
     * @return array<string, array{0: \Closure}>
     */
    public static function rejectedUploads(): array
    {
        return [
            'a php script renamed as an image' => [fn () => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')],
            'a pdf' => [fn () => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')],
            'an oversized image' => [fn () => UploadedFile::fake()->image('huge.jpg')->size(5000)],
        ];
    }

    #[DataProvider('rejectedUploads')]
    public function test_unsafe_or_oversized_uploads_are_rejected(\Closure $file): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/services', $this->servicePayload(['image' => $file()]))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('services', ['name' => 'Balayage']);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_replacing_an_image_removes_the_old_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post('/admin/services', $this->servicePayload([
            'image' => UploadedFile::fake()->image('first.jpg'),
        ]));

        $service = Service::where('name', 'Balayage')->firstOrFail();
        $original = $service->image_path;

        $this->actingAs($this->admin())->patch("/admin/services/{$service->id}", $this->servicePayload([
            'service_category_id' => $service->service_category_id,
            'image' => UploadedFile::fake()->image('second.jpg'),
        ]));

        $service->refresh();

        $this->assertNotSame($original, $service->image_path);
        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($service->image_path);
    }

    public function test_an_image_can_be_removed_without_uploading_another(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post('/admin/services', $this->servicePayload([
            'image' => UploadedFile::fake()->image('first.jpg'),
        ]));

        $service = Service::where('name', 'Balayage')->firstOrFail();
        $original = $service->image_path;

        $this->actingAs($this->admin())->patch("/admin/services/{$service->id}", $this->servicePayload([
            'service_category_id' => $service->service_category_id,
            'remove_image' => true,
        ]));

        $this->assertNull($service->fresh()->image_path);
        Storage::disk('public')->assertMissing($original);
    }
}
