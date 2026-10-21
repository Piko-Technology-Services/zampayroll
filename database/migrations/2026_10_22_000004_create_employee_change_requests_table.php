<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changes an employee asks for that HR must check first (e.g. a new bank account,
 * so salaries can't be redirected by someone who got hold of a portal password).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_change_requests')) {
            return;
        }
        Schema::create('employee_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('bank');
            $table->json('changes');      // field => new value
            $table->json('previous')->nullable(); // field => value at the time of asking
            $table->json('documents')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->foreignId('requested_by')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_comment', 500)->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_change_requests');
    }
};
