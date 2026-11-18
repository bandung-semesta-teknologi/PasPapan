<?php

use App\Livewire\User\AttendanceHistoryComponent;
use App\Livewire\User\ScanComponent;
use App\Models\Attendance;
use App\Models\Barcode;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\OfflineAttendanceSyncService;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setTime(16, 0));
    Setting::updateOrCreate(['key' => 'feature.require_photo'], ['value' => '0']);
    Setting::flushCache('feature.require_photo');
    $this->employee = User::factory()->create();
    $this->checkpoint = Barcode::factory()->create([
        'latitude' => -6.2,
        'longitude' => 106.8,
        'radius' => 200,
    ]);
    $this->shift = Shift::create(['name' => 'Morning', 'start_time' => '08:00', 'end_time' => '17:00']);
    $this->attendance = Attendance::create([
        'user_id' => $this->employee->id,
        'barcode_id' => $this->checkpoint->id,
        'shift_id' => $this->shift->id,
        'date' => now()->toDateString(),
        'time_in' => today()->setTime(8, 0),
        'status' => 'present',
        'approval_status' => Attendance::STATUS_APPROVED,
        'note' => 'Catatan sebelumnya',
    ]);
});

test('web clock out stores activity separately from early leave reason', function () {
    Livewire::actingAs($this->employee)->test(ScanComponent::class)
        ->call('scan', $this->checkpoint->value, -6.2, 106.8, null, 'Keperluan keluarga', "  Menyelesaikan laporan.\nKoordinasi tim.  ")
        ->assertReturned(true);

    expect($this->attendance->fresh())
        ->time_out->not->toBeNull()
        ->note->toBe('Keperluan keluarga')
        ->activity_details->toBe("Menyelesaikan laporan.\nKoordinasi tim.");
});

test('web clock out allows empty activity details', function () {
    Livewire::actingAs($this->employee)->test(ScanComponent::class)
        ->call('scan', $this->checkpoint->value, -6.2, 106.8)
        ->assertReturned(true);

    expect($this->attendance->fresh())
        ->time_out->not->toBeNull()
        ->note->toBe('Catatan sebelumnya')
        ->activity_details->toBeNull();
});

test('web clock out rejects activity longer than limit without recording checkout', function () {
    Livewire::actingAs($this->employee)->test(ScanComponent::class)
        ->call('scan', $this->checkpoint->value, -6.2, 106.8, null, null, str_repeat('a', 5001));

    expect($this->attendance->fresh())->time_out->toBeNull()->activity_details->toBeNull();
});

test('device clock out stores activity details', function () {
    Sanctum::actingAs($this->employee, deviceApiAbilities());

    $this->postJson('/api/device/barcode', [
        'barcode_data' => $this->checkpoint->value,
        'latitude' => -6.2,
        'longitude' => 106.8,
        'activity_details' => 'Menyelesaikan laporan harian.',
    ])->assertOk()->assertJsonPath('action', 'check_out');

    expect($this->attendance->fresh())->activity_details->toBe('Menyelesaikan laporan harian.');
});

test('device clock out rejects invalid activity details', function ($activity) {
    Sanctum::actingAs($this->employee, deviceApiAbilities());

    $this->postJson('/api/device/barcode', [
        'barcode_data' => $this->checkpoint->value,
        'latitude' => -6.2,
        'longitude' => 106.8,
        'activity_details' => $activity,
    ])->assertUnprocessable()->assertJsonValidationErrors('activity_details');

    expect($this->attendance->fresh())->time_out->toBeNull();
})->with([fn () => str_repeat('a', 5001), fn () => ['invalid']]);

test('offline clock out sync stores activity and preserves it on retry', function () {
    $item = [
        'client_uuid' => 'activity-out-001',
        'barcode_data' => $this->checkpoint->value,
        'latitude' => -6.2,
        'longitude' => 106.8,
        'timestamp' => now()->subMinute()->toDateTimeString(),
        'activity_details' => 'Mengerjakan laporan offline.',
    ];
    $service = app(OfflineAttendanceSyncService::class);
    $result = $service->sync($this->employee, [$item]);
    $retry = $service->sync($this->employee, [$item]);

    expect($result[0]['status'])->toBe('processed')
        ->and($result[0]['action'])->toBe('check_out')
        ->and($retry[0]['status'])->toBe('processed')
        ->and($this->attendance->fresh()->activity_details)->toBe('Mengerjakan laporan offline.');
});

test('attendance history displays escaped activity details', function () {
    $this->attendance->update(['activity_details' => '<script>alert(1)</script> Laporan selesai']);

    Livewire::actingAs($this->employee)->test(AttendanceHistoryComponent::class)
        ->call('show', $this->attendance->id)
        ->assertSee(__('Activity details'))
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Laporan selesai', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});
