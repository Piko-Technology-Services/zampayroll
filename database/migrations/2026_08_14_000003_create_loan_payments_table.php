<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();

            // Null for manual payments/adjustments not tied to a payroll deduction.
            $table->foreignId('payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2); // running balance snapshot at this point in time

            // deduction | manual_payment | adjustment | write_off
            $table->string('type', 20);
            $table->string('note', 255)->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['loan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
