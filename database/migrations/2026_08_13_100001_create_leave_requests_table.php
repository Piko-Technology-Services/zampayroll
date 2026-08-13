<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 2); // business days requested, excludes non-working days/holidays

            $table->text('reason')->nullable();

            // pending | approved | rejected
            $table->string('status', 20)->default('pending');
            $table->string('hr_comment', 500)->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
