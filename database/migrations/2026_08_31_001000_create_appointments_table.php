<?php

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // Human-quotable identifier and opaque QR payload. Both are random so
            // neither leaks customer identity or appointment volume.
            $table->string('reference', 32)->unique();
            $table->string('qr_token', 64)->nullable()->unique();

            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();

            // Datetimes are stored in UTC; config('salon.timezone') is the salon
            // wall clock used to interpret and present them.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->enum('status', AppointmentStatus::values())
                ->default(AppointmentStatus::Pending->value);
            $table->enum('source', AppointmentSource::values())
                ->default(AppointmentSource::Online->value);

            // Snapshot totals derived from appointment items at booking time.
            $table->unsignedSmallInteger('total_duration_minutes');
            $table->decimal('total_price', 10, 2);

            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('booked_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            // Set on the NEW appointment created by a reschedule, pointing back
            // at the appointment it replaced.
            $table->foreignId('rescheduled_from_id')->nullable()
                ->constrained('appointments')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Primary conflict-detection path: find a staff member's blocking
            // appointments overlapping a candidate window.
            $table->index(['staff_id', 'status', 'starts_at', 'ends_at']);

            // Customer-facing history and upcoming lists.
            $table->index(['customer_id', 'starts_at']);

            // Calendar and dashboard queries filter by status over a date range.
            $table->index(['status', 'starts_at']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
