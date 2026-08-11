<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('service');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('method')->default('mobile_money');
            $table->string('proof_path');
            $table->boolean('confirmed_sent')->default(false);
            $table->text('comment')->nullable();
            $table->string('status')->default('pending'); // pending | verified | rejected — for admin review later
            $table->timestamp('admin_notified_at')->nullable();
            $table->timestamp('user_notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
