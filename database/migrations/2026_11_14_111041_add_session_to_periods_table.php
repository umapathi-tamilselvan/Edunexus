<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. A period can belong to a session (morning or
     * afternoon); the session's attendance is taken at its first teaching
     * period. Existing periods have no session, so nothing changes for them.
     */
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->string('session', 16)->nullable()->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn('session');
        });
    }
};
