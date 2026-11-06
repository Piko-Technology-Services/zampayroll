<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Proof-of-payment columns for manual (bank / mobile money) payments.
 * 2. The old "free forever up to 10 employees" plan becomes the 2-month free
 *    experience. Existing free companies with no paid plan get a 30-day
 *    grace period from this deploy, so nobody is limited overnight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_payments', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('reference');
                $table->timestamp('reviewed_at')->nullable()->after('paid_at');
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            }
        });

        DB::table('companies')
            ->where(fn ($q) => $q->where('subscription_status', 'free')->orWhereNull('subscription_status'))
            ->whereNull('billing_type')
            ->whereNull('trial_ends_at')
            ->update([
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays(30),
            ]);
    }

    public function down(): void
    {
        Schema::table('billing_payments', function (Blueprint $table) {
            foreach (['proof_path', 'reviewed_at', 'reviewed_by'] as $col) {
                if (Schema::hasColumn('billing_payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
