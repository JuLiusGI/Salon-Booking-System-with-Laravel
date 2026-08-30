<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->date('hired_on')->nullable();
            $table->boolean('is_active')->default(true);

            // Whether customers may choose this person as a preferred stylist.
            // Receptionists and admins have staff records but are not bookable.
            $table->boolean('is_bookable')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // The public team page and the booking stylist picker both read this.
            $table->index(['is_active', 'is_bookable', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
