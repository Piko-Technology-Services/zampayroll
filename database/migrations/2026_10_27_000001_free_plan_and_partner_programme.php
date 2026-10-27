<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Free plan: companies with up to `free_limit` active employees use ZamPayroll
 *    free. The 7-day trial only starts when they go over the limit.
 * 2. Partner programme: partners (resellers) with a unique code, the companies
 *    they referred, and the commission earned on those companies' paid invoices.
 *
 * Everything here lives in the central (landlord) database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('code', 20)->unique();
                $table->string('name');
                $table->string('email');
                $table->string('phone', 50)->nullable();
                $table->string('partner_type', 40);                // hr_firm | hr_practitioner | hr_student | freelancer | accountant | other
                $table->string('firm_name')->nullable();
                $table->string('city', 100)->nullable();
                $table->text('pitch')->nullable();                 // how they plan to find clients
                $table->string('status', 20)->default('pending');  // pending | active | suspended
                $table->decimal('commission_rate', 5, 2)->nullable(); // overrides the platform rate when set
                $table->string('payout_method', 20)->nullable();   // mobile_money | bank
                $table->text('payout_details')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('partner_commissions')) {
            Schema::create('partner_commissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('partner_id')->index();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('invoice_id')->unique();  // one commission per paid invoice
                $table->string('invoice_number', 30);
                $table->decimal('base_amount', 12, 2);              // invoice amount before VAT
                $table->decimal('rate', 5, 2);
                $table->decimal('amount', 12, 2);
                $table->string('status', 20)->default('pending');   // pending | paid | void
                $table->timestamp('paid_at')->nullable();
                $table->string('payout_reference', 120)->nullable();
                $table->timestamps();
            });
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->string('subscription_status', 20)->default('free')->change();
            if (! Schema::hasColumn('companies', 'partner_id')) {
                $table->unsignedBigInteger('partner_id')->nullable()->index();
                $table->string('partner_code', 20)->nullable();
                $table->timestamp('referred_at')->nullable();
            }
        });

        // Companies still on a trial with a small team move to the free plan
        // (and keep their 7 days for when they grow past the limit).
        $limit = (int) (DB::table('platform_settings')->where('key', 'free_limit')->value('value') ?: config('billing.free_limit', 10));
        $active = DB::table('employees')->where('employment_status', 'Active')->selectRaw('company_id, COUNT(*) n')->groupBy('company_id')->pluck('n', 'company_id');
        foreach (DB::table('companies')->where('subscription_status', 'trialing')->whereNull('billing_type')->get(['id', 'tenancy_mode']) as $c) {
            if ($c->tenancy_mode === 'dedicated') {
                continue; // private workspaces are larger companies; leave them as they are
            }
            if ((int) ($active[$c->id] ?? 0) <= $limit) {
                DB::table('companies')->where('id', $c->id)->update(['subscription_status' => 'free', 'trial_ends_at' => null]);
            }
        }
    }

    public function down(): void
    {
        DB::table('companies')->where('subscription_status', 'free')->update(['subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(7)]);
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['partner_id', 'partner_code', 'referred_at']);
        });
        Schema::dropIfExists('partner_commissions');
        Schema::dropIfExists('partners');
    }
};
