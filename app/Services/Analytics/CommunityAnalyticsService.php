<?php

namespace App\Services\Analytics;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class CommunityAnalyticsService
{
    /**
     * @return array{
     *     month:int,
     *     year:int,
     *     period:string,
     *     monthLabel:string,
     *     employeeCount:int,
     *     totals:array{records:int,present:int,late:int,approved_leave:int,pending_leave:int,rejected_leave:int,other:int},
     *     daily:array<int,array{date:string,day:int,records:int,present:int,late:int,approved_leave:int,pending_leave:int,rejected_leave:int,other:int}>
     * }
     */
    public function forPeriod(User $actor, int $month, int $year): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        $daily = [];
        foreach (range(1, $end->day) as $day) {
            $date = $start->setDay($day);
            $daily[$date->toDateString()] = [
                'date' => $date->toDateString(),
                'day' => $day,
                'records' => 0,
                'present' => 0,
                'late' => 0,
                'approved_leave' => 0,
                'pending_leave' => 0,
                'rejected_leave' => 0,
                'other' => 0,
            ];
        }

        $totals = [
            'records' => 0,
            'present' => 0,
            'late' => 0,
            'approved_leave' => 0,
            'pending_leave' => 0,
            'rejected_leave' => 0,
            'other' => 0,
        ];

        $rows = Attendance::query()
            ->whereHas('user', fn (Builder $query) => $query
                ->where('group', 'user')
                ->managedBy($actor))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->select(['date', 'status', 'approval_status'])
            ->selectRaw('COUNT(*) as record_count')
            ->groupBy('date', 'status', 'approval_status')
            ->get();

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->date)->toDateString();
            if (! isset($daily[$date])) {
                continue;
            }

            $count = (int) $row->record_count;
            $key = $this->statusKey((string) $row->status, (string) $row->approval_status);

            $daily[$date]['records'] += $count;
            $daily[$date][$key] += $count;
            $totals['records'] += $count;
            $totals[$key] += $count;
        }

        $employeeCount = User::query()
            ->where('group', 'user')
            ->where('employment_status', User::EMPLOYMENT_STATUS_ACTIVE)
            ->managedBy($actor)
            ->count();

        return [
            'month' => $month,
            'year' => $year,
            'period' => $start->format('Y-m'),
            'monthLabel' => $start->translatedFormat('F Y'),
            'employeeCount' => $employeeCount,
            'totals' => $totals,
            'daily' => array_values($daily),
        ];
    }

    private function statusKey(string $status, string $approvalStatus): string
    {
        if ($status === 'present') {
            return 'present';
        }

        if ($status === 'late') {
            return 'late';
        }

        if (in_array($status, ['sick', 'excused', 'permission', 'leave'], true)) {
            return match ($approvalStatus) {
                Attendance::STATUS_APPROVED => 'approved_leave',
                Attendance::STATUS_REJECTED => 'rejected_leave',
                default => 'pending_leave',
            };
        }

        return 'other';
    }
}
