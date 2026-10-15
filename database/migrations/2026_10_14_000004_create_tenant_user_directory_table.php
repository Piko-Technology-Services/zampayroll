<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Landlord-only lookup: which dedicated workspace does an email belong to?
     * Lets people sign in / reset passwords from the main domain and get routed
     * to their company's sub-domain. (The table also exists, unused, inside
     * dedicated databases because they share the same migrations.)
     */
    public function up(): void
    {
        if (Schema::hasTable('tenant_user_directory')) {
            return;
        }

        Schema::create('tenant_user_directory', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_directory');
    }
};
