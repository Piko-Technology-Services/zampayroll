<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('company_email'); // email used to verify the applicant

            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('hours', 5, 2);

            // normal (1.5x, working day) | double (2x, holiday/non-working day)
            $table->string('type', 20);
            $table->decimal('rate_multiplier', 3, 2);
            $table->decimal('daily_rate', 10, 2)->nullable();  // salary/26 snapshot at time of application
            $table->decimal('amount', 10, 2)->nullable();       // computed payout

            $table->string('comment', 500)->nullable();

            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->string('hr_comment', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
