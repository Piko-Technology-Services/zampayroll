<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loans: approval and payout (disbursement) are separate, recorded steps.
 * Repayments are only deducted from pay once the money has actually been paid out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'disbursed_at')) {
                $table->foreignId('approved_by')->nullable()->after('created_by');
                $table->timestamp('approved_at')->nullable()->after('approved_by');
                $table->date('disbursed_at')->nullable()->after('approved_at');
                $table->foreignId('disbursed_by')->nullable()->after('disbursed_at');
                $table->string('disbursement_method', 30)->nullable()->after('disbursed_by'); // bank_transfer | cash | mobile_money | cheque
                $table->string('disbursement_reference', 120)->nullable()->after('disbursement_method');
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable();
                $table->string('cancel_reason', 500)->nullable();
            }
        });

        Schema::table('loan_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable();
                $table->string('cancel_reason', 500)->nullable();
                $table->foreignId('recorded_by')->nullable();
            }
        });

        // Loans already on the ledger were being deducted, so treat them as paid out on their start date.
        DB::table('loans')->whereNull('disbursed_at')->whereIn('status', ['active', 'completed', 'paused', 'written_off'])
            ->update(['disbursed_at' => DB::raw('start_date')]);

        // The built-in Payroll Officer role gets the new "record loan payouts" permission.
        if (Schema::hasTable('company_roles')) {
            foreach (DB::table('company_roles')->where('key', 'payroll')->get() as $role) {
                $perms = json_decode($role->permissions ?? '[]', true) ?: [];
                if (in_array('loans.manage', $perms, true) && ! in_array('loans.disburse', $perms, true)) {
                    $perms[] = 'loans.disburse';
                    DB::table('company_roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($perms))]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_at', 'disbursed_at', 'disbursed_by', 'disbursement_method', 'disbursement_reference', 'cancelled_at', 'cancelled_by', 'cancel_reason']);
        });
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancel_reason', 'recorded_by']);
        });
    }
};
