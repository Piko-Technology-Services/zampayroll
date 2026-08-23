<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_requests', 'installment_amount')) {
                // The amount the employee proposes to have deducted each
                // installment. Required for monthly/bi_monthly/other plans
                // so the system can calculate a payoff schedule up front;
                // not required for once_off (full amount, single deduction).
                $table->decimal('installment_amount', 10, 2)->nullable()->after('payment_plan_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            if (Schema::hasColumn('loan_requests', 'installment_amount')) {
                $table->dropColumn('installment_amount');
            }
        });
    }
};
