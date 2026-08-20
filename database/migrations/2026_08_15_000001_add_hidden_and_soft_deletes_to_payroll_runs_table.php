<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            // "Hidden" — archived out of the main dashboard, but NOT
            // deleted. Distinct from trash: hidden runs are still fully
            // intact and just filtered from the default index() query.
            if (! Schema::hasColumn('payroll_runs', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->after('status');
            }

            // "Trash" — soft delete. Recoverable until permanently deleted
            // from the Trash view.
            if (! Schema::hasColumn('payroll_runs', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_runs', 'hidden_at')) {
                $table->dropColumn('hidden_at');
            }
            if (Schema::hasColumn('payroll_runs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
