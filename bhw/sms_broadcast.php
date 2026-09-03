<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';
require_once __DIR__ . '/../models/Sms.php';
require_once __DIR__ . '/../services/SemaphoreSmsService.php';

$user = requireRole(['bhw', 'midwife']);

$errors = [];
$result = null;
$purokOptions = getDistinctPurokZones($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $message = sanitizeInput($_POST['message'] ?? '');
    $purokZone = sanitizeInput($_POST['purok_zone'] ?? '');

    if ($message === '') {
        $errors[] = 'Message cannot be empty.';
    } elseif (strlen($message) > 480) {
        $errors[] = 'Message is too long (max 480 characters, roughly 3 SMS segments).';
    }

    if (!$errors) {
        $filters = ['status' => 'active'];
        if ($purokZone !== '') {
            $filters['purok_zone'] = $purokZone;
        }
        $recipients = getSeniorCitizens($conn, $filters, 'full_name', 'ASC', 5000, 0);

        $result = sendBroadcastSms($conn, $recipients, $message);
        logActivity(
            $conn,
            (int) $user['id'],
            'sms_broadcast',
            sprintf('Sent broadcast to %s: %d sent, %d failed, %d skipped (no number).', $purokZone ?: 'all Puroks/Zones', $result['sent'], $result['failed'], $result['skipped'])
        );
    }
}

$recentLogs = getSmsLogs($conn, 25, 0);

$pageTitle = 'SMS Broadcast';
$activeMenu = 'sms';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1>SMS Broadcast</h1>
        <div class="page-subtitle">Send a program update to all beneficiaries or a filtered subset</div>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 small"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($result): ?>
    <div class="alert alert-info">
        <strong><?= $result['sent'] ?></strong> sent, <strong><?= $result['failed'] ?></strong> failed,
        <strong><?= $result['skipped'] ?></strong> beneficiaries skipped (no registered mobile number),
        out of <?= $result['total'] ?> matching beneficiaries.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold mb-3">Compose Message</h2>
            <form method="post">
                <?php csrfField(); ?>
                <div class="mb-2">
                    <label class="form-label small">Recipients</label>
                    <select name="purok_zone" class="form-select form-select-sm">
                        <option value="">All active beneficiaries</option>
                        <?php foreach ($purokOptions as $pz): ?>
                            <option value="<?= h($pz) ?>"><?= h($pz) ?> only</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Message</label>
                    <textarea name="message" class="form-control form-control-sm" rows="4" maxlength="480" required placeholder="e.g. Reminder: the health center will be closed on..."><?= h($_POST['message'] ?? '') ?></textarea>
                    <div class="form-text">Beneficiaries without a registered mobile number are automatically skipped and reported after sending.</div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Send this message now?');"><i class="bi bi-send"></i> Send Broadcast</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold mb-3">Recent SMS Log</h2>
            <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
            <table class="table table-sm">
                <thead><tr><th>To</th><th>Type</th><th>Status</th><th>Sent</th></tr></thead>
                <tbody>
                <?php if (!$recentLogs): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">No SMS sent yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td class="small"><?= h($log['senior_name'] ?? $log['phone_number']) ?></td>
                        <td><span class="badge text-bg-light border"><?= h(ucfirst($log['type'])) ?></span></td>
                        <td><span class="badge <?= $log['status'] === 'sent' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= h(ucfirst($log['status'])) ?></span></td>
                        <td class="small"><?= formatDateTime($log['sent_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
