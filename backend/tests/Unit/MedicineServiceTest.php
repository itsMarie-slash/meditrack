<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit;

use MediTrack\Repositories\DistributionScheduleRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\SeniorCitizenRepository;
use MediTrack\Repositories\SmsNotificationRepository;
use MediTrack\Repositories\SystemSettingRepository;
use MediTrack\Services\MedicineService;
use MediTrack\Services\SmsService;
use PHPUnit\Framework\TestCase;

/**
 * Covers FR-3/FR-4/FR-6: a stock-in that crosses the low-stock threshold
 * must create a distribution schedule and SMS every beneficiary assigned
 * that medicine; one that doesn't cross it must not.
 */
final class MedicineServiceTest extends TestCase
{
    public function testStockCrossingAboveThresholdCreatesScheduleAndNotifiesBeneficiaries(): void
    {
        $medicines = $this->createMock(MedicineRepository::class);
        $medicines->method('addStock')->willReturn([
            'old_quantity' => 0,
            'new_quantity' => 100,
            'threshold' => 50,
            'batch_id' => 1,
        ]);

        $schedules = $this->createMock(DistributionScheduleRepository::class);
        $schedules->expects($this->once())->method('create')->willReturn(42);

        $seniorCitizens = $this->createMock(SeniorCitizenRepository::class);
        $seniorCitizens->method('findByAssignedMedicine')->willReturn([
            ['id' => 1, 'full_name' => 'Juana Dela Cruz', 'mobile_number' => '09171234567'],
            ['id' => 2, 'full_name' => 'Pedro Santos', 'mobile_number' => '09181234567'],
        ]);

        $smsNotifications = $this->createMock(SmsNotificationRepository::class);
        $smsNotifications->expects($this->exactly(2))->method('record');

        $settings = $this->createMock(SystemSettingRepository::class);
        $settings->method('get')->willReturnCallback(fn(string $key, string $default) => $default);

        $sms = $this->createMock(SmsService::class);
        $sms->method('send')->willReturn(['success' => true]);

        $service = new MedicineService($medicines, $schedules, $seniorCitizens, $smsNotifications, $settings, $sms);
        $result = $service->receiveStock(1, 'Amlodipine 5mg', 100, 'B-001', '2027-01-01', '2026-09-08');

        $this->assertTrue($result['schedule_created']);
        $this->assertSame(42, $result['schedule_id']);
        $this->assertSame(2, $result['sms_sent']);
        $this->assertSame(0, $result['sms_failed']);
    }

    public function testStockStayingBelowThresholdDoesNotCreateSchedule(): void
    {
        $medicines = $this->createMock(MedicineRepository::class);
        $medicines->method('addStock')->willReturn([
            'old_quantity' => 5,
            'new_quantity' => 15,
            'threshold' => 50,
            'batch_id' => 2,
        ]);

        $schedules = $this->createMock(DistributionScheduleRepository::class);
        $schedules->expects($this->never())->method('create');

        $seniorCitizens = $this->createMock(SeniorCitizenRepository::class);
        $seniorCitizens->expects($this->never())->method('findByAssignedMedicine');

        $smsNotifications = $this->createMock(SmsNotificationRepository::class);
        $settings = $this->createMock(SystemSettingRepository::class);
        $sms = $this->createMock(SmsService::class);

        $service = new MedicineService($medicines, $schedules, $seniorCitizens, $smsNotifications, $settings, $sms);
        $result = $service->receiveStock(1, 'Amlodipine 5mg', 10, 'B-002', '2027-01-01', '2026-09-08');

        $this->assertFalse($result['schedule_created']);
        $this->assertNull($result['schedule_id']);
        $this->assertSame(0, $result['sms_sent']);
    }

    public function testStockAlreadyAboveThresholdBeforeAndAfterDoesNotRetrigger(): void
    {
        // Restocking a medicine that was never low (old > threshold already)
        // must not spam another schedule/SMS batch.
        $medicines = $this->createMock(MedicineRepository::class);
        $medicines->method('addStock')->willReturn([
            'old_quantity' => 80,
            'new_quantity' => 180,
            'threshold' => 50,
            'batch_id' => 3,
        ]);

        $schedules = $this->createMock(DistributionScheduleRepository::class);
        $schedules->expects($this->never())->method('create');

        $service = new MedicineService(
            $medicines,
            $schedules,
            $this->createMock(SeniorCitizenRepository::class),
            $this->createMock(SmsNotificationRepository::class),
            $this->createMock(SystemSettingRepository::class),
            $this->createMock(SmsService::class),
        );
        $result = $service->receiveStock(1, 'Amlodipine 5mg', 100, 'B-003', '2027-01-01', '2026-09-08');

        $this->assertFalse($result['schedule_created']);
    }

    public function testFailedSmsIsRecordedAsFailedNotSent(): void
    {
        $medicines = $this->createMock(MedicineRepository::class);
        $medicines->method('addStock')->willReturn([
            'old_quantity' => 0, 'new_quantity' => 100, 'threshold' => 50, 'batch_id' => 1,
        ]);
        $schedules = $this->createMock(DistributionScheduleRepository::class);
        $schedules->method('create')->willReturn(1);

        $seniorCitizens = $this->createMock(SeniorCitizenRepository::class);
        $seniorCitizens->method('findByAssignedMedicine')->willReturn([
            ['id' => 1, 'full_name' => 'Juana Dela Cruz', 'mobile_number' => '09171234567'],
        ]);

        $smsNotifications = $this->createMock(SmsNotificationRepository::class);
        $smsNotifications->expects($this->once())->method('record')
            ->with(1, 1, $this->anything(), 'failed', $this->anything());

        $settings = $this->createMock(SystemSettingRepository::class);
        $settings->method('get')->willReturnCallback(fn(string $key, string $default) => $default);

        $sms = $this->createMock(SmsService::class);
        $sms->method('send')->willReturn(['success' => false, 'error' => 'Gateway timeout']);

        $service = new MedicineService($medicines, $schedules, $seniorCitizens, $smsNotifications, $settings, $sms);
        $result = $service->receiveStock(1, 'Amlodipine 5mg', 100, 'B-004', '2027-01-01', '2026-09-08');

        $this->assertSame(0, $result['sms_sent']);
        $this->assertSame(1, $result['sms_failed']);
    }
}
