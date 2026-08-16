<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'daily_rate_salary')) {
                // Basic + earnings, spread over a 26-day pay cycle.
                // Same formula/value as leave_days_value; kept as its own
                // column since overtime and other modules reference "daily
                // rate" independently of leave.
                $anchor = Schema::hasColumn('employees', 'natural_gross_salary')
                    ? 'natural_gross_salary'
                    : 'salary';

                $table->decimal('daily_rate_salary', 10, 2)
                    ->nullable()
                    ->after($anchor);
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'daily_rate_salary')) {
                $table->dropColumn('daily_rate_salary');
            }
        });
    }
};
