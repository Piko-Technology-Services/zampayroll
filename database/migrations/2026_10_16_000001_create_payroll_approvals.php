<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll approval chain (set by the company admin) and a full history of
 * what happened to each pay run: prepared → submitted → approved/sent back → locked → paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_approval_steps')) {
            Schema::create('payroll_approval_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedSmallInteger('position');
                $table->string('name', 80);
                $table->string('role_key', 60)->nullable();     // anyone with this role…
                $table->unsignedBigInteger('user_id')->nullable(); // …or one named person
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payroll_run_events')) {
            Schema::create('payroll_run_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('payroll_run_id')->index();
                $table->string('action', 30);            // calculated, submitted, approved, sent_back, recalled, locked, paid
                $table->unsignedSmallInteger('step_position')->nullable();
                $table->string('step_name', 80)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('comment')->nullable();
                $table->json('snapshot')->nullable();    // totals at that moment
                $table->timestamps();
            });
        }

        Schema::table('payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_runs', 'current_step')) {
                $table->unsignedSmallInteger('current_step')->nullable()->after('status');
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->boolean('include_overtime')->default(true);
                $table->json('approval_chain')->nullable(); // chain frozen at submission
            }
        });

        Schema::table('payroll_run_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_run_adjustments', 'overtime_request_id')) {
                $table->unsignedBigInteger('overtime_request_id')->nullable()->after('loan_id');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'payroll_allow_self_approval')) {
                $table->boolean('payroll_allow_self_approval')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_approval_steps');
        Schema::dropIfExists('payroll_run_events');
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['current_step', 'submitted_at', 'submitted_by', 'paid_at', 'paid_by', 'include_overtime', 'approval_chain']);
        });
        Schema::table('payroll_run_adjustments', fn (Blueprint $t) => $t->dropColumn('overtime_request_id'));
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('payroll_allow_self_approval'));
    }
};
