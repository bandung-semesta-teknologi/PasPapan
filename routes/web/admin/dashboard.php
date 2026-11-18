<?php

use App\Http\Controllers\Admin\AdminRootRedirectController;
use App\Http\Controllers\Admin\DashboardController;
use App\Livewire\Admin\CommunityAnalyticsDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', AdminRootRedirectController::class)
    ->can('accessAdminPanel');

Route::get('/dashboard', DashboardController::class)->name('admin.dashboard')->can('viewAdminDashboard');

Route::livewire('/command-center', 'admin.command-center')->name('admin.command-center')->can('viewCommandCenter');
Route::livewire('/inbox', 'admin.manager-inbox')->name('admin.inbox')->can('accessAdminPanel');
Route::livewire('/notifications', 'admin.notifications-page')->name('admin.notifications')->can('manageAdminNotifications');
Route::livewire('/analytics', CommunityAnalyticsDashboard::class)
    ->name('admin.analytics')
    ->can('viewAnalyticsDashboard');
