<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A teacher's sections are looked up by teacher_id and read section_id
     * from the same index, so the lookup never touches the table rows.
     */
    public function up(): void
    {
        Schema::table('section_subject_teachers', function (Blueprint $table) {
            $table->index(['teacher_id', 'section_id']);
        });

        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->index(['teacher_id', 'section_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('section_subject_teachers', function (Blueprint $table) {
            $table->dropIndex(['teacher_id', 'section_id']);
        });

        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropIndex(['teacher_id', 'section_id']);
        });
    }
};
