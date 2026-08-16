<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'leave_days_balance')) {
                $table->decimal('leave_days_balance', 5, 2)
                    ->nullable()
                    ->default(null)
                    ->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'leave_days_balance')) {
                // Reverting to NOT NULL with a default of 0 will fail if any
                // existing rows are currently NULL — backfill first.
                $table->decimal('leave_days_balance', 5, 2)
                    ->nullable(false)
                    ->default(0)
                    ->change();
            }
        });
    }
};