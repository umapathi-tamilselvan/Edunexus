<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When an exam's marks were published. A published exam's marks and
     * maximum marks are locked until someone with exam.unlock reopens it.
     * Null for every existing exam, so nothing is locked by this change.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('ends_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
