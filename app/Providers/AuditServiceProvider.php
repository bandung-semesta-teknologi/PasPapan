<?php

namespace App\Providers;

use App\Contracts\AuditServiceInterface;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\CashAdvance;
use App\Models\CompanyAsset;
use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Models\Reimbursement;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SystemBackupRun;
use App\Models\User;
use App\Observers\SensitiveModelAuditObserver;
use App\Observers\SystemBackupRunObserver;
use App\Services\Audit\CommunityAuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditServiceInterface::class, CommunityAuditService::class);
    }

    public function boot(): void
    {
        SystemBackupRun::observe(SystemBackupRunObserver::class);

        foreach ([
            User::class,
            Payroll::class,
            PayrollComponent::class,
            Role::class,
            AttendanceCorrection::class,
            Attendance::class,
            Reimbursement::class,
            CashAdvance::class,
            CompanyAsset::class,
            Setting::class,
        ] as $modelClass) {
            $modelClass::observe(SensitiveModelAuditObserver::class);
        }

        Event::listen(Login::class, function (Login $event) {
            ActivityLog::record('Login Successful', 'User logged in.');
        });

        Event::listen(Failed::class, function (Failed $event) {
            ActivityLog::record(
                'Login Failed',
                'Failed login attempt for email: '.($event->credentials['email'] ?? 'unknown')
            );
        });
    }
}
