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
        // Gapless counters (admission numbers, later receipt numbers). Rows
        // are locked FOR UPDATE inside the transaction that uses the number,
        // so a rollback never leaves a gap.
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
