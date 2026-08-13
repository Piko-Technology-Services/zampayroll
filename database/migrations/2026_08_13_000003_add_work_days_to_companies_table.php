<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // ISO weekday numbers that count as working days: 1=Mon ... 7=Sun
            // Defaults to Mon-Fri when null (handled in application code).
            $table->json('work_days')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('work_days');
        });
    }
};
