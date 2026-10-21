<?php

namespace App\Console\Commands;

use App\Services\Billing\Subscriptions;
use Illuminate\Console\Command;

/** Daily: post-paid invoices, renewal invoices, reminders, limiting unpaid accounts, re-checking DPO payments. */
class BillingRun extends Command
{
    protected $signature = 'billing:run';
    protected $description = 'Run ZamPayroll subscription billing (invoices, reminders, limits, DPO checks)';

    public function handle(Subscriptions $subs): int
    {
        $stats = $subs->runDaily(fn ($m) => $this->warn($m));
        $this->info('Billing run: ' . json_encode($stats));

        return self::SUCCESS;
    }
}
