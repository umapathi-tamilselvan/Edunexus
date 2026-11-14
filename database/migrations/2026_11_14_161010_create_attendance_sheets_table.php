<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per section, day and period (0 for the whole day): who submitted
     * the sheet and when (D41). The first save sets both; later edits do not
     * change them. Existing sheets have no row until their next save, so the
     * submitter of an older sheet is recorded from that save on. Step 0.5's
     * snapshot columns and backfill are not part of this migration.
     */
    public function up(): void
    {
        Schema::create('attendance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->date('date');
            $table->unsignedInteger('period')->default(0);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['section_id', 'date', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sheets');
    }
};
