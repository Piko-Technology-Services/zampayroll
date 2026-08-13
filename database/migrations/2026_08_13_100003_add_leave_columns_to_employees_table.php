<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Annual entitlement, e.g. 24 days/year. Guarded — your payslip view
            // already references leave_days_balance / leave_days_value, so this
            // only adds the entitlement column if it isn't already there.
            if (! Schema::hasColumn('employees', 'leave_days_entitled')) {
                $table->decimal('leave_days_entitled', 5, 2)->default(24)->after('salary');
            }
            if (! Schema::hasColumn('employees', 'leave_days_balance')) {
                $table->decimal('leave_days_balance', 5, 2)->default(0)->after('leave_days_entitled');
            }
            if (! Schema::hasColumn('employees', 'leave_days_value')) {
                // Payout rate per unused leave day, e.g. salary / 26
                $table->decimal('leave_days_value', 10, 2)->nullable()->after('leave_days_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            foreach (['leave_days_entitled', 'leave_days_balance', 'leave_days_value'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
