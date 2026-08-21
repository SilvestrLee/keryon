<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * K-CHURCHWEB-001B §23 — service times are institutionally meaningful
 * beyond Website (would remain true if Website disappeared), so this is a
 * Church-owned institutional table. Deliberately not an event/calendar
 * system — `time` is a simple display string ("10:00 AM", "9:00 AM &
 * 11:00 AM"), not a recurrence engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_service_times', function (Blueprint $table) {
            $table->id();

            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('label');
            $table->string('day_of_week')->nullable();
            $table->string('time');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['church_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_service_times');
    }
};
