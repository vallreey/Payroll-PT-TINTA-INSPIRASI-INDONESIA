<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DUMMY: jadwal meeting/event di dashboard + peserta (pivot).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['meeting', 'event'])->default('meeting');
            $table->dateTime('start_at')->index();
            $table->dateTime('end_at');
            $table->string('location')->nullable();      // mis. "Google Meet"
            $table->string('meeting_url')->nullable();   // wajib http(s) saat input
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('schedule_attendees', function (Blueprint $table) {
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->primary(['schedule_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_attendees');
        Schema::dropIfExists('schedules');
    }
};
