<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Money is integer paise, never a float.
     */
    public function up(): void
    {
        // What a due is for: tuition, transport, exam…
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // One per payment, numbered without gaps per financial year.
        Schema::create('fee_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 40)->unique();
            $table->string('financial_year', 7);
            $table->foreignId('enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->date('paid_on');
            // A retried request or gateway webhook finds the receipt it already made.
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->string('gateway_payment_id', 100)->nullable()->unique();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The ledger: append-only. A student's balance is the sum of amounts.
        Schema::create('fee_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->string('type', 20);
            $table->foreignId('fee_head_id')->nullable()->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->date('due_on')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('receipt_id')->nullable()->constrained('fee_receipts')->restrictOnDelete();
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('fee_entries')->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['enrollment_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_entries');
        Schema::dropIfExists('fee_receipts');
        Schema::dropIfExists('fee_heads');
    }
};
