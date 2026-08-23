<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('swift_code', 15)->nullable();
            $table->string('sort_code', 20)->nullable(); // most ZM banks don't publish a national sort code — left for HR to fill in if theirs uses one
            // Alternate spellings/short names employees might type into their bank_name field
            // (e.g. Employee record says "Barclays" — this lets us still match it to Absa).
            $table->json('aliases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the common Zambian banks. SWIFT/BIC codes sourced from public SWIFT
        // directories (cross-checked against a Standard Chartered reference list) as
        // of Aug 2026 — verify against the bank directly before relying on these for
        // live payment files, and correct here if a bank has since changed theirs.
        $now = now();
        DB::table('banks')->insert([
            ['name' => 'Zambia National Commercial Bank (Zanaco)', 'short_name' => 'Zanaco', 'swift_code' => 'ZNCOZMLU', 'aliases' => json_encode(['Zanaco', 'ZNCB', 'Zambia National Commercial Bank']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Absa Bank Zambia', 'short_name' => 'Absa', 'swift_code' => 'BARCZMLX', 'aliases' => json_encode(['Absa', 'Barclays', 'Barclays Bank Zambia']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Stanbic Bank Zambia', 'short_name' => 'Stanbic', 'swift_code' => 'SBICZMLX', 'aliases' => json_encode(['Stanbic']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Standard Chartered Bank Zambia', 'short_name' => 'Stanchart', 'swift_code' => 'SCBLZMLX', 'aliases' => json_encode(['Standard Chartered', 'Stanchart', 'SCB']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'First National Bank Zambia', 'short_name' => 'FNB', 'swift_code' => 'FIRNZMLU', 'aliases' => json_encode(['FNB', 'First National Bank']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Access Bank Zambia', 'short_name' => 'Access Bank', 'swift_code' => 'AZAMZMLU', 'aliases' => json_encode(['Access Bank', 'Access']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ecobank Zambia', 'short_name' => 'Ecobank', 'swift_code' => 'ECOCZMLU', 'aliases' => json_encode(['Ecobank']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Indo Zambia Bank', 'short_name' => 'Indo Zambia', 'swift_code' => 'INZAZMLX', 'aliases' => json_encode(['Indo Zambia', 'IZB']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Citibank Zambia', 'short_name' => 'Citibank', 'swift_code' => 'CITIZMLU', 'aliases' => json_encode(['Citibank', 'Citi']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Investrust Bank', 'short_name' => 'Investrust', 'swift_code' => 'VSTRZMLU', 'aliases' => json_encode(['Investrust']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'First Capital Bank Zambia', 'short_name' => 'First Capital', 'swift_code' => 'ICZAZMLU', 'aliases' => json_encode(['First Capital Bank', 'International Commercial Bank', 'ICB']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bank of China Zambia', 'short_name' => 'BOC', 'swift_code' => 'BKCHZMLU', 'aliases' => json_encode(['Bank of China']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cavmont Bank', 'short_name' => 'Cavmont', 'swift_code' => 'CVMTZMLU', 'aliases' => json_encode(['Cavmont']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
