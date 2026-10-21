<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ZamPayroll's own billing (trials, subscriptions, invoices, DPO payments),
 * platform settings and client support tickets.
 * These tables always live in the central (landlord) database, even for
 * companies on a private sub-domain, so the ZamPayroll admin console can see everything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'subscription_status')) {
                $table->string('plan', 30)->nullable();                     // starter | business
                $table->string('billing_type', 20)->nullable();             // prepaid | postpaid
                $table->string('billing_cycle', 20)->default('monthly');    // monthly | annual (prepaid)
                $table->string('subscription_status', 20)->default('trialing'); // trialing | active | past_due | suspended | cancelled
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('paid_until')->nullable();                // prepaid: access paid up to
                $table->date('billing_anchor')->nullable();                 // postpaid: invoice day of month
                $table->timestamp('last_billed_at')->nullable();
                $table->string('billing_email')->nullable();
                $table->timestamp('subdomain_requested_at')->nullable();
                $table->string('requested_subdomain', 63)->nullable();
                $table->text('billing_notes')->nullable();
            }
        });

        // Existing companies get a fresh 7-day trial from the day this is deployed.
        DB::table('companies')->whereNull('trial_ends_at')->update([
            'trial_ends_at' => now()->addDays(7),
            'subscription_status' => 'trialing',
        ]);

        if (! Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->string('key', 80)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('number', 30)->unique();
                $table->string('kind', 20);                       // subscription | usage | other
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->json('lines');
                $table->decimal('subtotal', 12, 2);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('vat', 12, 2)->default(0);
                $table->decimal('total', 12, 2);
                $table->string('currency', 3)->default('ZMW');
                $table->string('status', 20)->default('open');   // open | paid | void
                $table->date('due_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('emailed_at')->nullable();
                $table->unsignedSmallInteger('reminders_sent')->default(0);
                $table->json('meta')->nullable();                 // e.g. billing cycle, employee count
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('billing_payments')) {
            Schema::create('billing_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->string('provider', 20)->default('dpo');   // dpo | manual
                $table->string('method', 20)->nullable();         // card | mobile_money | bank | cash
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('ZMW');
                $table->string('status', 20)->default('pending'); // pending | paid | failed | cancelled
                $table->string('trans_token', 100)->nullable()->unique();
                $table->string('trans_ref', 100)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('network', 20)->nullable();
                $table->string('result_code', 10)->nullable();
                $table->string('result_explanation')->nullable();
                $table->string('reference')->nullable();          // manual payments: bank/receipt ref
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('number', 20)->unique();
                $table->string('subject');
                $table->string('category', 30)->default('question');  // question | problem | billing | feature
                $table->string('priority', 10)->default('normal');    // low | normal | urgent
                $table->string('status', 20)->default('open');        // open | waiting | resolved | closed
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->timestamp('last_reply_at')->nullable();
                $table->boolean('last_reply_by_staff')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('support_ticket_id')->index();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('author_name')->nullable();
                $table->boolean('from_staff')->default(false);
                $table->text('body');
                $table->json('attachments')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('platform_settings');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['plan', 'billing_type', 'billing_cycle', 'subscription_status', 'trial_ends_at', 'paid_until', 'billing_anchor',
                'last_billed_at', 'billing_email', 'subdomain_requested_at', 'requested_subdomain', 'billing_notes']);
        });
    }
};
