<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('company_email');

            $table->decimal('amount', 12, 2);

            // monthly | bi_monthly | once_off | other
            $table->string('payment_plan', 20);
            $table->string('payment_plan_note', 255)->nullable(); // free-text detail when plan = other

            $table->text('reason')->nullable();
            $table->json('documents')->nullable();

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
        Schema::dropIfExists('loan_requests');
    }
};
