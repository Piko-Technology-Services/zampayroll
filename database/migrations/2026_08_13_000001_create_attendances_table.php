<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            // present | absent | sick | leave | holiday | other | non_working
            $table->string('status', 20)->default('present');

            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('hours_worked', 5, 2)->nullable();

            $table->string('note', 255)->nullable();

            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
