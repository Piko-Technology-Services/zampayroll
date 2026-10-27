<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Edited versions of NAPSA / NHIMA / ZRA returns, saved before export. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('statutory_returns')) {
            Schema::create('statutory_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('kind', 20);                 // napsa | nhima | zra
                $table->string('scope_key', 40);            // run:{id} | month:{Y-m}
                $table->unsignedBigInteger('payroll_run_id')->nullable();
                $table->string('period', 20)->nullable();
                $table->json('rows');
                $table->json('removed')->nullable();
                $table->unsignedBigInteger('edited_by')->nullable();
                $table->timestamp('exported_at')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'kind', 'scope_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_returns');
    }
};
