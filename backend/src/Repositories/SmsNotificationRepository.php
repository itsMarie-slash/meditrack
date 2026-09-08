<?php

declare(strict_types=1);

namespace MediTrack\Repositories;

use PDO;

final class SmsNotificationRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function record(int $seniorCitizenId, int $scheduleId, string $message, string $status, ?string $errorMessage = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sms_notifications (senior_citizen_id, schedule_id, message, status, error_message)
             VALUES (:senior_citizen_id, :schedule_id, :message, :status, :error_message)'
        );
        $stmt->execute([
            'senior_citizen_id' => $seniorCitizenId,
            'schedule_id' => $scheduleId,
            'message' => $message,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
