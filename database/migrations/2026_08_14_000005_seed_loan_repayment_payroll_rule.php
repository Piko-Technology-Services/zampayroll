<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_rules')->updateOrInsert(
            ['code' => 'D_LOAN'],
            [
                'name'                 => 'Loan Repayment',
                'type'                 => 'deduction',
                'category'             => 'loan',
                'formula_type'         => 'fixed',
                'value'                => 0,
                'is_statutory'         => false,
                'requires_assignment'  => true,
                'affects_gross'        => false,
                'active'               => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('payroll_rules')->where('code', 'D_LOAN')->delete();
    }
};
