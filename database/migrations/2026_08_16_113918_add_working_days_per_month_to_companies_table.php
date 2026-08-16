<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'working_days_per_month')) {
                // Used as the divisor for daily-rate / leave-day-value
                // calculations (e.g. natural_gross_salary / working_days_per_month).
                $table->unsignedTinyInteger('working_days_per_month')
                    ->nullable()
                    ->default(26)
                    ->after('work_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'working_days_per_month')) {
                $table->dropColumn('working_days_per_month');
            }
        });
    }
};