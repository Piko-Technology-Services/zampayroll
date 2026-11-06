<?php

namespace App\Console\Commands;

use App\Services\Compliance\TemplateLibrary;
use Illuminate\Console\Command;

class ComplianceSyncTemplates extends Command
{
    protected $signature = 'compliance:sync-templates';

    protected $description = 'Add or update the ZamPayroll master contract and policy templates (company copies are never changed)';

    public function handle(): int
    {
        $n = TemplateLibrary::sync();
        $this->info("{$n} master template(s) added or updated.");

        return self::SUCCESS;
    }
}
