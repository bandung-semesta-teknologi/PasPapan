<?php

namespace App\Providers;

use App\Contracts\AttendanceServiceInterface;
use App\Contracts\PayrollServiceInterface;
use App\Contracts\ReportingServiceInterface;
use App\Services\Attendance\CommunityService;
use App\Services\Payroll\CommunityPayrollService;
use App\Services\Reporting\CommunityReportingService;
use Illuminate\Support\ServiceProvider;

class EnterpriseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AttendanceServiceInterface::class, CommunityService::class);

        $this->app->singleton(PayrollServiceInterface::class, CommunityPayrollService::class);

        $this->app->singleton(ReportingServiceInterface::class, CommunityReportingService::class);
    }
}
