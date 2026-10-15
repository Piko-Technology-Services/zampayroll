<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'employee_id')) {
                // Links a self-service portal account to its employee record.
                $table->foreignId('employee_id')->nullable()->after('company_id')
                    ->constrained('employees')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 50)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'portal_invited_at')) {
                $table->timestamp('portal_invited_at')->nullable();
            }
        });

        // Email confirmation is now required to sign in. Accounts that existed
        // before this feature are treated as already confirmed so nobody is locked out.
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn(['phone', 'must_change_password', 'last_login_at', 'portal_invited_at']);
        });
    }
};
