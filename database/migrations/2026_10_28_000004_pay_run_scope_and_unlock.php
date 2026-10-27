<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several pay runs per month: each run can cover everyone, some departments or picked people.
 * Approved runs can be unlocked again (audited).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_runs', 'run_type')) {
                $table->string('run_type', 30)->default('regular')->after('alias');
            }
            if (! Schema::hasColumn('payroll_runs', 'scope')) {
                $table->json('scope')->nullable()->after('run_type');
            }
            if (! Schema::hasColumn('payroll_runs', 'unlocked_at')) {
                $table->timestamp('unlocked_at')->nullable();
                $table->unsignedBigInteger('unlocked_by')->nullable();
                $table->unsignedInteger('unlock_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['run_type', 'scope', 'unlocked_at', 'unlocked_by', 'unlock_count']);
        });
    }
};
