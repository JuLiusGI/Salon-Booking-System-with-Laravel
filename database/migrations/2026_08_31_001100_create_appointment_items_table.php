<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();

            // Nulled rather than blocked if a service is ever hard-deleted. The
            // snapshot columns below keep the historical appointment accurate on
            // their own, so the appointment survives the service disappearing.
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot taken at booking time. Editing a service later must never
            // retroactively change what a past appointment cost or how long it
            // was (MASTER_SPEC section 7, Appointment Item).
            $table->string('service_name');
            $table->decimal('service_price', 10, 2);
            $table->unsignedSmallInteger('service_duration_minutes');

            // Reserved for a future requirement where different staff perform
            // different services within one appointment. This is left null for
            // now, and the appointment's staff member is used for every item.
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['appointment_id', 'position']);
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_items');
    }
};
