<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime: breaks, overnight shifts, company-set rates, HR entry on someone's behalf,
 * cancellation, and a link to the payroll run that paid it (so it is never paid twice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('overtime_requests', 'break_minutes')) {
                $table->unsignedSmallInteger('break_minutes')->default(0)->after('end_time');
                $table->decimal('hourly_rate', 10, 2)->nullable()->after('daily_rate');
                $table->string('rate_base', 12)->nullable()->after('hourly_rate'); // basic | gross
                $table->decimal('requested_hours', 5, 2)->nullable()->after('hours'); // what was asked, if HR changed it
                $table->foreignId('recorded_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable();
                $table->string('cancel_reason', 500)->nullable();
                $table->foreignId('payroll_run_id')->nullable()->index(); // the payroll that paid it
            }
        });
        // Longer type keys for company-defined rate categories.
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->string('type', 40)->change();
            $table->decimal('rate_multiplier', 5, 2)->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'overtime_settings')) {
                $table->json('overtime_settings')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('overtime_settings'));
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropColumn(['break_minutes', 'hourly_rate', 'rate_base', 'requested_hours', 'recorded_by', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'payroll_run_id']);
        });
    }
};
