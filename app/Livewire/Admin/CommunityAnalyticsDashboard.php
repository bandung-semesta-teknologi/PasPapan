<?php

namespace App\Livewire\Admin;

use App\Services\Analytics\CommunityAnalyticsService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class CommunityAnalyticsDashboard extends Component
{
    protected CommunityAnalyticsService $analytics;

    #[Url(history: true)]
    public $month;

    #[Url(history: true)]
    public $year;

    public function boot(CommunityAnalyticsService $analytics): void
    {
        $this->analytics = $analytics;
        Gate::authorize('viewAnalyticsDashboard');
    }

    public function mount(): void
    {
        $this->month = $this->normalizeMonth($this->month);
        $this->year = $this->normalizeYear($this->year);
    }

    public function updatedMonth(mixed $month): void
    {
        $this->month = $this->normalizeMonth($month);
    }

    public function updatedYear(mixed $year): void
    {
        $this->year = $this->normalizeYear($year);
    }

    public function render()
    {
        return view('livewire.admin.community-analytics-dashboard', $this->analytics->forPeriod(
            auth()->user(),
            (int) $this->month,
            (int) $this->year,
        ));
    }

    private function normalizeMonth(mixed $month): int
    {
        $month = filter_var($month, FILTER_VALIDATE_INT);

        return $month !== false && $month >= 1 && $month <= 12
            ? $month
            : (int) now()->month;
    }

    private function normalizeYear(mixed $year): int
    {
        $year = filter_var($year, FILTER_VALIDATE_INT);
        $currentYear = (int) now()->year;

        return $year !== false && $year >= 2000 && $year <= $currentYear
            ? $year
            : $currentYear;
    }
}
