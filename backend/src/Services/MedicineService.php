<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Repositories\DistributionScheduleRepository;
use MediTrack\Repositories\MedicineRepository;
use MediTrack\Repositories\SeniorCitizenRepository;
use MediTrack\Repositories\SmsNotificationRepository;
use MediTrack\Repositories\SystemSettingRepository;

/**
 * Implements FR-3/FR-4/FR-6: a stock-in that brings a medicine's quantity
 * back above its low-stock threshold automatically creates a distribution
 * schedule and SMS-notifies every beneficiary currently assigned that
 * medicine (see docs/02-architecture.md §3 and docs/01-requirements-analysis.md §13.5).
 */
final class MedicineService
{
    public function __construct(
        private readonly MedicineRepository $medicines,
        private readonly DistributionScheduleRepository $schedules,
        private readonly SeniorCitizenRepository $seniorCitizens,
        private readonly SmsNotificationRepository $smsNotifications,
        private readonly SystemSettingRepository $settings,
        private readonly SmsService $sms,
    ) {
    }

    /**
     * @return array{stock:array{old_quantity:int,new_quantity:int,threshold:int,batch_id:int},
     *               schedule_created:bool,schedule_id:?int,sms_sent:int,sms_failed:int}
     */
    public function receiveStock(int $medicineId, string $medicineName, int $quantity, string $batchNumber, string $expirationDate, string $dateReceived): array
    {
        $stock = $this->medicines->addStock($medicineId, $quantity, $batchNumber, $expirationDate, $dateReceived);

        $crossedAboveThreshold = $stock['old_quantity'] <= $stock['threshold'] && $stock['new_quantity'] > $stock['threshold'];

        $result = [
            'stock' => $stock,
            'schedule_created' => false,
            'schedule_id' => null,
            'sms_sent' => 0,
            'sms_failed' => 0,
        ];

        if (!$crossedAboveThreshold) {
            return $result;
        }

        $leadDays = max(1, (int) $this->settings->get('schedule_lead_days', '3'));
        $scheduledDate = (new \DateTimeImmutable("+{$leadDays} days"))->format('Y-m-d');
        $timeSlot = $this->settings->get('default_time_slot', '9:00 AM - 12:00 PM');
        $venue = $this->settings->get('default_distribution_venue', 'Barangay New Bulatukan Health Center');

        $scheduleId = $this->schedules->create($medicineId, $scheduledDate, $timeSlot, $venue, true);
        $result['schedule_created'] = true;
        $result['schedule_id'] = $scheduleId;

        $template = $this->settings->get(
            'sms_schedule_template',
            'MediTrack: Your {medicine} pickup is on {date} at {time}, {venue}. Please arrive on time.'
        );
        $message = strtr($template, [
            '{medicine}' => $medicineName,
            '{date}' => $scheduledDate,
            '{time}' => $timeSlot,
            '{venue}' => $venue,
        ]);

        $beneficiaries = $this->seniorCitizens->findByAssignedMedicine($medicineId);
        foreach ($beneficiaries as $beneficiary) {
            $outcome = $this->sms->send($beneficiary['mobile_number'], $message);
            if ($outcome['success']) {
                $this->smsNotifications->record((int) $beneficiary['id'], $scheduleId, $message, 'sent');
                $result['sms_sent']++;
            } else {
                $this->smsNotifications->record((int) $beneficiary['id'], $scheduleId, $message, 'failed', $outcome['error'] ?? null);
                $result['sms_failed']++;
            }
        }

        return $result;
    }
}
