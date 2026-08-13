<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('leave_type', 40)->default('annual')->after('employee_id');
            $table->string('company_email')->nullable()->after('leave_type'); // email used to verify the applicant
            $table->date('return_date')->nullable()->after('end_date');
            $table->string('supervisor_email')->nullable()->after('reason');
            $table->json('documents')->nullable()->after('supervisor_email'); // supporting files
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['leave_type', 'company_email', 'return_date', 'supervisor_email', 'documents']);
        });
    }
};
