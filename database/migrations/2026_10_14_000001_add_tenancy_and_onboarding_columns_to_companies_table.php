<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // shared = lives in the main DB (<= 10 employees)
            // dedicated = own sub-domain + own database (> 10 employees)
            if (! Schema::hasColumn('companies', 'tenancy_mode')) {
                $table->string('tenancy_mode', 20)->default('shared')->after('name');
            }
            if (! Schema::hasColumn('companies', 'subdomain')) {
                $table->string('subdomain', 63)->nullable()->unique()->after('tenancy_mode');
            }
            if (! Schema::hasColumn('companies', 'database_name')) {
                $table->string('database_name', 64)->nullable()->after('subdomain');
            }
            // pending_verification | awaiting_database | provisioning | active | failed | suspended
            if (! Schema::hasColumn('companies', 'status')) {
                $table->string('status', 30)->default('active')->after('database_name');
            }

            // Captured by the registration wizard
            if (! Schema::hasColumn('companies', 'industry')) {
                $table->string('industry', 50)->nullable();
            }
            if (! Schema::hasColumn('companies', 'employee_count_range')) {
                $table->string('employee_count_range', 20)->nullable();
            }
            if (! Schema::hasColumn('companies', 'pay_frequency')) {
                $table->string('pay_frequency', 20)->nullable()->default('monthly');
            }
            if (! Schema::hasColumn('companies', 'pay_day')) {
                $table->unsignedTinyInteger('pay_day')->nullable();
            }
            if (! Schema::hasColumn('companies', 'currency')) {
                $table->string('currency', 3)->default('ZMW');
            }
            if (! Schema::hasColumn('companies', 'interested_modules')) {
                $table->json('interested_modules')->nullable();
            }
            if (! Schema::hasColumn('companies', 'heard_about_us')) {
                $table->string('heard_about_us', 100)->nullable();
            }
            if (! Schema::hasColumn('companies', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable();
            }
            if (! Schema::hasColumn('companies', 'provisioned_at')) {
                $table->timestamp('provisioned_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['subdomain']);
            $table->dropColumn([
                'tenancy_mode', 'subdomain', 'database_name', 'status', 'industry',
                'employee_count_range', 'pay_frequency', 'pay_day', 'currency',
                'interested_modules', 'heard_about_us', 'onboarding_completed_at', 'provisioned_at',
            ]);
        });
    }
};
