<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('admission_no', 30)->unique();
            $table->string('name');
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Encrypted with the client's APP_KEY; phone_hash makes it searchable.
            $table->text('phone');
            $table->char('phone_hash', 64)->index();
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 20);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->primary(['guardian_id', 'student_id']);
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('roll_no')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
            $table->unique(['section_id', 'roll_no']);
            $table->index(['academic_year_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
