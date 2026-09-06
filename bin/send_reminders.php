<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

define('REMINDER_ROOT', dirname(__DIR__));
require_once REMINDER_ROOT . '/public_html/config.php';
require_once REMINDER_ROOT . '/public_html/functions.php';
require_once REMINDER_ROOT . '/public_html/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$pdo = db();

$stmt = $pdo->query("
    SELECT r.*, p.default_email_lead, p.email_override, p.email_cache
    FROM reminders r
    LEFT JOIN reminder_user_prefs p ON p.user_id = r.user_id
    WHERE r.status = 'active'
      AND r.email_notify = 1
      AND DATE_SUB(r.remind_at, INTERVAL COALESCE(r.email_lead_time, p.default_email_lead, 1440) MINUTE) <= NOW()
      AND NOT EXISTS (
          SELECT 1 FROM email_log el
          WHERE el.reminder_id = r.id AND el.scheduled_for = r.remind_at
      )
");

foreach ($stmt->fetchAll() as $r) {
    $emailTo = $r['email_override'] ?: ($r['email_cache'] ?? '');
    if (!$emailTo || !filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {
        error_log('[reminder-cron] Kein gültiges E-Mail für user_id=' . $r['user_id'] . ', reminder_id=' . $r['id']);
        continue;
    }

    $status = 'failed';
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($emailTo);
        $mail->Subject = 'Erinnerung: ' . $r['title'];
        $mail->isHTML(false);
        $mail->Body = "Hallo,\n\ndein Reminder ist fällig:\n\n"
            . '📌 ' . $r['title'] . "\n"
            . '🕐 ' . date('d.m.Y H:i', strtotime($r['remind_at'])) . "\n"
            . ($r['description'] ? "\n" . $r['description'] . "\n" : '')
            . "\n→ https://reminder.schnueddels.de/\n";
        $mail->send();
        $status = 'sent';
    } catch (MailException $e) {
        error_log('[reminder-cron] Mail failed reminder_id=' . $r['id'] . ': ' . $e->getMessage());
    }

    $pdo->prepare('INSERT INTO email_log (reminder_id, scheduled_for, sent_at, status) VALUES (?,?,NOW(),?)')
        ->execute([$r['id'], $r['remind_at'], $status]);
}

if (VAPID_PUBLIC_KEY && VAPID_PRIVATE_KEY) {
    $stmt = $pdo->query("
        SELECT r.id, r.title, r.description, ps.endpoint, ps.p256dh, ps.auth
        FROM reminders r
        INNER JOIN push_subscriptions ps ON ps.user_id = r.user_id
        WHERE r.status IN ('active','snoozed')
          AND (
              (r.status = 'active' AND r.remind_at <= NOW())
              OR (r.status = 'snoozed' AND r.snoozed_until <= NOW())
          )
    ");

    $toPush = $stmt->fetchAll();
    if (!empty($toPush)) {
        $webPush = new WebPush(['VAPID' => [
            'subject' => VAPID_SUBJECT,
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ]]);

        foreach ($toPush as $r) {
            $sub = Subscription::create([
                'endpoint' => $r['endpoint'],
                'keys' => ['p256dh' => $r['p256dh'], 'auth' => $r['auth']],
            ]);
            $payload = json_encode([
                'title' => $r['title'],
                'body' => $r['description'] ?? '',
                'url' => 'https://reminder.schnueddels.de/',
            ]);
            $webPush->queueNotification($sub, $payload);
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')
                    ->execute([$report->getEndpoint()]);
            }
        }
    }
}

$stmt = $pdo->query("
    SELECT * FROM reminders
    WHERE status = 'active' AND remind_at <= NOW() AND recurrence != 'none'
");

foreach ($stmt->fetchAll() as $r) {
    $next = calcNext($r['remind_at'], $r['recurrence']);
    if ($r['recurrence_end'] && $next > $r['recurrence_end'] . ' 23:59:59') {
        $pdo->prepare("UPDATE reminders SET status = 'done' WHERE id = ?")->execute([$r['id']]);
    } else {
        $pdo->prepare("UPDATE reminders SET remind_at = ?, status = 'active', snoozed_until = NULL WHERE id = ?")
            ->execute([$next, $r['id']]);
    }
}

$pdo->exec("
    UPDATE reminders
    SET status = 'done'
    WHERE status = 'active'
      AND remind_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND recurrence = 'none'
");

function calcNext(string $date, string $recurrence): string {
    $ts = strtotime($date);
    return match($recurrence) {
        'daily' => date('Y-m-d H:i:s', strtotime('+1 day', $ts)),
        'weekly' => date('Y-m-d H:i:s', strtotime('+1 week', $ts)),
        'monthly' => date('Y-m-d H:i:s', strtotime('+1 month', $ts)),
        'yearly' => date('Y-m-d H:i:s', strtotime('+1 year', $ts)),
        default => $date,
    };
}
