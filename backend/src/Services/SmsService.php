<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Config\Env;

/**
 * Thin client for the Semaphore SMS gateway (https://semaphore.co).
 * Set SMS_DRY_RUN=true in .env to log messages instead of sending them —
 * useful in development before a Semaphore account/API key exists.
 */
class SmsService
{
    private const ENDPOINT = 'https://api.semaphore.co/api/v4/messages';

    /** @return array{success:bool,error?:string} */
    public function send(string $mobileNumber, string $message): array
    {
        if (Env::bool('SMS_DRY_RUN', true)) {
            error_log("[MediTrack][SMS dry-run] to {$mobileNumber}: {$message}");
            return ['success' => true];
        }

        $apiKey = Env::get('SEMAPHORE_API_KEY', '');
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'SMS gateway is not configured (missing SEMAPHORE_API_KEY).'];
        }

        $payload = http_build_query([
            'apikey' => $apiKey,
            'number' => $mobileNumber,
            'message' => $message,
            'sendername' => Env::get('SEMAPHORE_SENDER_NAME', 'MediTrack'),
        ]);

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status >= 400) {
            return ['success' => false, 'error' => $error ?: "SMS gateway returned HTTP {$status}."];
        }

        return ['success' => true];
    }
}
