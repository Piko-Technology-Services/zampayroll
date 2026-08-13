<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->decimal('days_taken', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_records');
    }
};
