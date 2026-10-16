<?php

namespace App\Providers;

use App\Models\Company;
use App\Support\Tenancy;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->makeQueueTenantAware();

        // @can('payroll.run') / ->can(...) in Blade and code use the company's role permissions.
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            if (\App\Support\Permissions::exists($ability) && method_exists($user, 'hasPermission')) {
                return $user->hasPermission($ability);
            }

            return null;
        });
    }

    /**
     * Queued jobs (payslip emails, leave notifications...) remember which
     * tenant dispatched them, and the worker switches to that tenant's
     * database / company scope before the job runs.
     */
    private function makeQueueTenantAware(): void
    {
        Queue::createPayloadUsing(function () {
            return [
                'tenant' => [
                    'company_id' => Tenancy::companyId(),
                    'dedicated'  => Tenancy::isDedicated(),
                ],
            ];
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            // The sync driver runs inline inside the request — context is already set.
            if ($event->connectionName === 'sync') {
                return;
            }

            $tenant = $event->job->payload()['tenant'] ?? null;

            Tenancy::switchToLandlord();

            if (! $tenant || empty($tenant['company_id'])) {
                return;
            }

            if (! empty($tenant['dedicated'])) {
                $company = Company::on(Tenancy::landlordConnection())->find($tenant['company_id']);

                if ($company && $company->database_name) {
                    Tenancy::switchToDedicated($company);
                    URL::forceRootUrl($company->workspaceUrl());
                }

                return;
            }

            Tenancy::setCompanyId((int) $tenant['company_id']);
            URL::forceRootUrl(Tenancy::centralUrl());
        });

        $reset = function ($event) {
            if ($event->connectionName === 'sync') {
                return;
            }
            Tenancy::switchToLandlord();
            URL::forceRootUrl(null);
        };

        Event::listen(JobProcessed::class, $reset);
        Event::listen(JobFailed::class, $reset);
    }
}
