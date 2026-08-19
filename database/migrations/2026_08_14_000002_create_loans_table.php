<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Null if this loan was entered directly by HR rather than
            // originating from an employee's public application.
            $table->foreignId('loan_request_id')->nullable()->constrained('loan_requests')->nullOnDelete();

            $table->decimal('principal_amount', 12, 2);
            $table->decimal('balance', 12, 2); // running balance, decremented by LoanRepaymentService

            // monthly | bi_monthly | once_off | other — same vocabulary as LoanRequest::PAYMENT_PLANS
            $table->string('payment_plan', 20);
            $table->string('payment_plan_note', 255)->nullable();

            // Required for monthly/bi_monthly/other; ignored for once_off
            // (once_off always deducts the full remaining balance in one go).
            $table->decimal('installment_amount', 10, 2)->nullable();

            $table->date('start_date');
            $table->date('next_deduction_date')->nullable();

            // active | completed | paused | written_off
            $table->string('status', 20)->default('active');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
