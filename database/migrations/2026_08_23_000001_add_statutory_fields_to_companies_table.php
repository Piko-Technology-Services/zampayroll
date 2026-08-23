<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // NAPSA — National Pension Scheme Authority employer registration number
            $table->string('napsa_employer_number')->nullable()->after('tpin');
            // NHIMA — National Health Insurance Management Authority employer/facility number
            $table->string('nhima_employer_number')->nullable()->after('napsa_employer_number');
            // PACRA — Patents and Companies Registration Agency business registration number
            $table->string('pacra_registration_number')->nullable()->after('nhima_employer_number');
            // WCFCB — Workers' Compensation Fund Control Board registration number
            $table->string('workers_comp_number')->nullable()->after('pacra_registration_number');
            // ZRA VAT number — optional, not every company is VAT-registered
            $table->string('vat_number')->nullable()->after('workers_comp_number');

            // A couple of extra fields that round out "company information" without
            // touching anything attendance-related (work_days / working_days_per_month
            // already exist and are managed from the Attendance settings page).
            $table->string('city')->nullable()->after('address');
            $table->string('province')->nullable()->after('city');
            $table->string('website')->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'napsa_employer_number',
                'nhima_employer_number',
                'pacra_registration_number',
                'workers_comp_number',
                'vat_number',
                'city',
                'province',
                'website',
                'logo_path',
            ]);
        });
    }
};
