<x-admin.page-shell
    :title="__('Attendance Analytics')"
    :description="__('Attendance records visible to your admin scope for the selected month.')"
    :show-description="true"
>
    <x-slot name="toolbar">
        <x-admin.page-tools
            :title="__('Filter period')"
            grid-class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2"
        >
            <div>
                <label for="analytics-month" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                    {{ __('Month') }}
                </label>
                <select id="analytics-month" wire:model.live="month" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    @foreach (range(1, 12) as $monthOption)
                        <option value="{{ $monthOption }}">{{ \Carbon\Carbon::create()->month($monthOption)->translatedFormat('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="analytics-year" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                    {{ __('Year') }}
                </label>
                <select id="analytics-year" wire:model.live="year" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    @foreach (range((int) now()->year, max(2000, (int) now()->year - 4)) as $yearOption)
                        <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                    @endforeach
                </select>
            </div>
        </x-admin.page-tools>
    </x-slot>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ $monthLabel }}</p>
        <div wire:loading role="status" aria-live="polite" class="text-sm text-primary-700 dark:text-primary-300">
            {{ __('Updating attendance summary…') }}
        </div>
    </div>

    <section aria-label="{{ __('Attendance summary') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => __('Active employees'), 'value' => $employeeCount],
            ['label' => __('Attendance records'), 'value' => $totals['records']],
            ['label' => __('Present records'), 'value' => $totals['present']],
            ['label' => __('Late records'), 'value' => $totals['late']],
        ] as $card)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ $card['label'] }}</h2>
                <p class="mt-2 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($card['value']) }}</p>
            </article>
        @endforeach
    </section>

    <section aria-labelledby="daily-attendance-heading" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 id="daily-attendance-heading" class="text-base font-semibold text-slate-950 dark:text-white">{{ __('Daily attendance records') }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('Counts recorded on each date. A date with no record is not treated as an absence.') }}</p>
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Present') }}: {{ number_format($totals['present']) }} · {{ __('Late') }}: {{ number_format($totals['late']) }} · {{ __('Approved leave') }}: {{ number_format($totals['approved_leave']) }}</p>
        </div>

        @if ($totals['records'] === 0)
            <div class="mt-5 rounded-lg bg-slate-50 px-4 py-8 text-center text-sm text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                {{ __('No attendance records for this period.') }}
            </div>
        @else
            @php($maxRecords = max(collect($daily)->max('records'), 1))
            <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4 lg:grid-cols-7" role="list" aria-label="{{ __('Daily attendance record counts for :period', ['period' => $monthLabel]) }}">
                @foreach ($daily as $day)
                    <div role="listitem" class="min-w-0 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $day['day'] }}</span>
                            <span class="tabular-nums text-slate-950 dark:text-white">{{ $day['records'] }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="progressbar" aria-label="{{ __('Attendance records on day :day', ['day' => $day['day']]) }}" aria-valuemin="0" aria-valuemax="{{ $maxRecords }}" aria-valuenow="{{ $day['records'] }}">
                            <div class="h-full rounded-full bg-primary-600 dark:bg-primary-400" style="width: {{ round(($day['records'] / $maxRecords) * 100, 1) }}%"></div>
                        </div>
                        <p class="mt-2 truncate text-xs text-slate-500 dark:text-slate-400">{{ __('Present') }} {{ $day['present'] }} · {{ __('Late') }} {{ $day['late'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($totals['approved_leave'] + $totals['pending_leave'] + $totals['rejected_leave'] + $totals['other'] > 0)
        <section aria-label="{{ __('Other attendance records') }}" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['label' => __('Approved leave'), 'value' => $totals['approved_leave']],
                ['label' => __('Pending leave requests'), 'value' => $totals['pending_leave']],
                ['label' => __('Rejected leave requests'), 'value' => $totals['rejected_leave']],
                ['label' => __('Other records'), 'value' => $totals['other']],
            ] as $card)
                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm text-slate-600 dark:text-slate-300">{{ $card['label'] }}</h2>
                    <p class="mt-2 text-xl font-semibold tabular-nums text-slate-950 dark:text-white">{{ number_format($card['value']) }}</p>
                </article>
            @endforeach
        </section>
    @endif
</x-admin.page-shell>
