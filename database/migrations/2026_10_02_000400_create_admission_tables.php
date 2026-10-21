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
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 30)->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('status', 20)->index();

            $table->string('student_name');
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->string('previous_school')->nullable();

            $table->string('guardian_name');
            // Encrypted with the client's APP_KEY; guardian_phone_hash makes it searchable.
            $table->text('guardian_phone');
            $table->char('guardian_phone_hash', 64)->index();
            $table->string('guardian_email')->nullable();
            $table->string('guardian_relation', 20);

            $table->text('notes')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('student_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        // DPDP: verifiable guardian consent for processing a child's data,
        // with the version of the privacy notice that was shown.
        Schema::create('guardian_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('notice_version', 20);
            $table->string('method', 20);
            $table->timestamp('consented_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'withdrawn_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_consents');
        Schema::dropIfExists('admission_applications');
    }
};
