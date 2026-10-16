<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding: employees can be created from partial data (pasted lists,
 * AI mode, migrations from other payroll systems) and completed later.
 * Only name + employee number stay mandatory at database level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->change();
            $table->string('gender')->nullable()->change();
            $table->string('nationality')->nullable()->default('Zambian')->change();
            $table->string('primary_phone')->nullable()->change();
            $table->string('position')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Left nullable on rollback: tightening could fail on rows created with partial data.
    }
};
