<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift rota: the company's shifts (defined by the admin) and who works which shift on which day
 * (set by HR for anyone, or by a supervisor for their own team).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name', 60);
                $table->string('code', 4);
                $table->time('start_time');
                $table->time('end_time');
                $table->unsignedSmallInteger('break_minutes')->default(0);
                $table->string('color', 7)->default('#00742D');
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'name']);
            });
        }

        if (! Schema::hasTable('shift_assignments')) {
            Schema::create('shift_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('employee_id');
                $table->date('date');
                $table->unsignedBigInteger('shift_id')->nullable(); // null = day off
                $table->string('note', 200)->nullable();
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'employee_id', 'date']);
                $table->index(['company_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
    }
};
