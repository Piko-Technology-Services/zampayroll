<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'total_loan_balance')) {
                // Cached sum of all this employee's ACTIVE loan balances.
                // Kept in sync by Loan::syncEmployeeCache() — never edit
                // directly; it's a denormalized read-shortcut, the real
                // source of truth is the loans table.
                $table->decimal('total_loan_balance', 12, 2)->default(0)->after('leave_days_value');
            }
            if (! Schema::hasColumn('employees', 'has_active_loan')) {
                $table->boolean('has_active_loan')->default(false)->after('total_loan_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            foreach (['total_loan_balance', 'has_active_loan'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
