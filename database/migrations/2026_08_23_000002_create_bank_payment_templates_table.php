<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_payment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // The bank whose bulk-upload portal this template's column layout matches
            // (the company's OWN disbursing bank) — nullable for a fully custom/unlabelled template.
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            $table->string('name'); // e.g. "Zanaco Bulk Payment File" or "Custom Upload — Aug 2026"
            $table->enum('source', ['preset', 'custom'])->default('preset');

            $table->string('delimiter', 5)->default(',');
            $table->boolean('include_header')->default(true);
            $table->unsignedTinyInteger('amount_decimal_places')->default(2);

            // Ordered array of column definitions:
            // [{ "header": "# Account Number", "field": "account_number", "prefix": "#", "suffix": "", "static_value": null }, ...]
            $table->json('columns');

            // The template actually used when a company exports — only one per company.
            $table->boolean('is_default')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_payment_templates');
    }
};
