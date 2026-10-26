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
        // An exam of one academic year, e.g. "Quarterly", taken by its classes.
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('max_marks');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
        });

        // A class's subject whose maximum marks differ from the exam's, e.g.
        // Quarterly, Class 1 Computer Science: 50.
        Schema::create('exam_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('max_marks');
            $table->timestamps();

            $table->unique(['exam_id', 'class_id', 'subject_id']);
        });

        // A student's marks in one subject of one exam, kept on the
        // enrollment like attendance and fees.
        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->decimal('marks', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'enrollment_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_marks');
        Schema::dropIfExists('exam_papers');
        Schema::dropIfExists('exams');
    }
};
