<?php

use App\Enums\ScheduleExceptionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();

            // Null means the exception applies to the whole salon
            // (a holiday or closure) rather than to one staff member.
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->enum('type', ScheduleExceptionType::values());
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // Only used when type is special_hours, where the exception replaces
            // the normal opening hours instead of blocking the period.
            $table->time('override_opens_at')->nullable();
            $table->time('override_closes_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            // The availability engine scans exceptions by date range, both for a
            // specific staff member and salon-wide.
            $table->index(['staff_id', 'starts_at', 'ends_at']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
    }
};
