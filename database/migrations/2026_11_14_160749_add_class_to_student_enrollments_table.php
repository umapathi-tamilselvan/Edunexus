<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An enrolment carries its class (D45), so a student can be in a class
     * before a section. Existing rows take the class of their section. The
     * section becomes optional until the student is distributed. The class
     * column stays nullable for this release, so code from before the change
     * still runs during rollout (D10).
     */
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->foreignId('class_id')->nullable()->after('academic_year_id')->constrained('classes')->restrictOnDelete();
        });

        DB::statement('UPDATE student_enrollments JOIN sections ON sections.id = student_enrollments.section_id SET student_enrollments.class_id = sections.class_id WHERE student_enrollments.class_id IS NULL');

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Fails if any enrolment has no section.
     */
    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable(false)->change();
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_id');
        });
    }
};
