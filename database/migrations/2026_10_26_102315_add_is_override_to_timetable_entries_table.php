<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A slot's teacher is the section's subject teacher unless set by hand.
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->boolean('is_override')->default(false)->after('teacher_id');
        });

        // Slots taught by someone other than the subject teacher were set by hand.
        DB::table('timetable_entries')
            ->whereNotNull('teacher_id')
            ->whereNotExists(fn (Builder $assignments) => $assignments
                ->from('section_subject_teachers')
                ->whereColumn('section_subject_teachers.section_id', 'timetable_entries.section_id')
                ->whereColumn('section_subject_teachers.subject_id', 'timetable_entries.subject_id')
                ->whereColumn('section_subject_teachers.teacher_id', 'timetable_entries.teacher_id'))
            ->update(['is_override' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropColumn('is_override');
        });
    }
};
