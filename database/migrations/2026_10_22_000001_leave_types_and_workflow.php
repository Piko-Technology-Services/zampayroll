<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave: company-configurable leave types, two-step approval (manager → HR, optional),
 * proper cancellation (kept as a record, not deleted) and an exact record of the
 * balance that was taken so it can be given back.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->index();
                $table->string('key', 40);
                $table->string('name', 80);
                $table->string('description', 255)->nullable();
                $table->boolean('is_paid')->default(true);
                $table->boolean('deducts_balance')->default(false);
                $table->boolean('requires_document')->default(false);
                // Only ask for a document when the request is longer than this many days (null = always).
                $table->unsignedSmallInteger('document_after_days')->nullable();
                // Yearly limit for types that don't use the annual balance (null = no limit set).
                $table->decimal('days_per_year', 6, 2)->nullable();
                $table->decimal('max_days_per_request', 6, 2)->nullable();
                $table->boolean('employees_can_apply')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'key']);
            });
        }

        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'stage')) {
                // manager → hr (only when the company wants HR to confirm manager approvals)
                $table->string('stage', 20)->nullable()->after('status');
                $table->foreignId('manager_approved_by')->nullable()->after('stage');
                $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
                $table->string('manager_comment', 500)->nullable()->after('manager_approved_at');
                $table->decimal('deducted_days', 6, 2)->default(0)->after('days');
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable();
                $table->boolean('cancelled_by_employee')->default(false);
                $table->string('cancel_reason', 500)->nullable();
                $table->foreignId('recorded_by')->nullable(); // HR entered it on the employee's behalf
            }
        });

        // Leave approved before this change took its days off the balance (any type) —
        // remember that so cancelling it gives the days back.
        \Illuminate\Support\Facades\DB::table('leave_requests')
            ->where('status', 'approved')->where('deducted_days', 0)
            ->whereIn('employee_id', fn ($q) => $q->select('id')->from('employees')->whereNotNull('leave_days_balance'))
            ->update(['deducted_days' => \Illuminate\Support\Facades\DB::raw('days')]);

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'leave_hr_confirm')) {
                $table->boolean('leave_hr_confirm')->default(false);
                $table->boolean('leave_allow_negative')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['leave_hr_confirm', 'leave_allow_negative']);
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['stage', 'manager_approved_by', 'manager_approved_at', 'manager_comment', 'deducted_days',
                'cancelled_at', 'cancelled_by', 'cancelled_by_employee', 'cancel_reason', 'recorded_by']);
        });
        Schema::dropIfExists('leave_types');
    }
};
