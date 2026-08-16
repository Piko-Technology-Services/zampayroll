<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Guarded — safe to run even if an earlier migration already
            // added some of these (e.g. from the leave module install).
            if (! Schema::hasColumn('employees', 'leave_days_entitled')) {
                $table->decimal('leave_days_entitled', 5, 2)->default(24)->after('salary');
            }
            if (! Schema::hasColumn('employees', 'leave_days_balance')) {
                $table->decimal('leave_days_balance', 5, 2)->default(0)->after('leave_days_entitled');
            }
            if (! Schema::hasColumn('employees', 'leave_days_value')) {
                // Optional manual override. When null, it's computed as
                // natural_gross_salary / 26 wherever it's needed.
                $table->decimal('leave_days_value', 10, 2)->nullable()->after('leave_days_balance');
            }
            if (! Schema::hasColumn('employees', 'natural_gross_salary')) {
                // Basic pay + all earnings. Drives leave day value and
                // overtime daily-rate calculations.
                $table->decimal('natural_gross_salary', 12, 2)->nullable()->after('leave_days_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            foreach (['leave_days_entitled', 'leave_days_balance', 'leave_days_value', 'natural_gross_salary'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
