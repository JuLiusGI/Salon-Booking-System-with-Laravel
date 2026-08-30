<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row configuration table. The application reads the first row and
        // the admin edits it; it is never expected to hold more than one record.
        Schema::create('booking_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('min_advance_minutes')->default(60);
            $table->unsignedSmallInteger('max_advance_days')->default(60);
            $table->unsignedSmallInteger('cancellation_deadline_hours')->default(24);
            $table->unsignedSmallInteger('reschedule_deadline_hours')->default(24);
            $table->unsignedSmallInteger('buffer_minutes')->default(10);
            $table->unsignedSmallInteger('slot_interval_minutes')->default(15);
            $table->unsignedSmallInteger('max_duration_minutes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rules');
    }
};
