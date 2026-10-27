<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payslip delivery: whether a payslip is on the self-service portal (published_at),
 * when/where it was emailed, and each company's default delivery choices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (! Schema::hasColumn('payrolls', 'published_at')) {
                $table->timestamp('published_at')->nullable();
                $table->timestamp('emailed_at')->nullable();
                $table->string('emailed_to', 500)->nullable();
            }
        });

        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'payslip_settings')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->json('payslip_settings')->nullable();
            });
        }

        // Payslips employees could already see stay visible.
        DB::table('payrolls')->whereIn('status', ['Approved', 'Paid', 'Finalized', 'Finalised'])->whereNull('published_at')
            ->update(['published_at' => DB::raw('COALESCE(updated_at, NOW())')]);
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['published_at', 'emailed_at', 'emailed_to']);
        });
        if (Schema::hasColumn('companies', 'payslip_settings')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('payslip_settings');
            });
        }
    }
};
