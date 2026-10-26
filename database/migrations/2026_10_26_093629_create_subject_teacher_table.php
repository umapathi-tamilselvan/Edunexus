<?php

use Illuminate\Database\Migrations\Migration;
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
        // The subjects each teacher teaches: one or more, replacing users.subject_id.
        Schema::create('subject_teacher', function (Blueprint $table) {
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['subject_id', 'user_id']);
            $table->index('user_id');
        });

        // Copy each teacher's one subject. Safe to rerun: users.subject_id stays
        // for one release, and a later migration copies again what the previous
        // release wrote meanwhile before v3.3 drops the column.
        DB::table('subject_teacher')->insertOrIgnoreUsing(
            ['subject_id', 'user_id'],
            DB::table('users')->whereNotNull('subject_id')->select(['subject_id', 'id']),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_teacher');
    }
};
