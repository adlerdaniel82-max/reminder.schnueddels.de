<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// CORS: erlaubt alle *.schnueddels.de Subdomains (für Widget-Integration)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https://[a-zA-Z0-9-]+\.schnueddels\.de$#', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/functions.php';

$user   = requireAuth();
$userId = (int)$user['id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

match(true) {
    $method === 'GET'  && $action === 'list'             => apiList($userId),
    $method === 'GET'  && $action === 'due'              => apiDue($userId),
    $method === 'GET'  && $action === 'prefs_get'        => apiPrefsGet($userId),
    $method === 'GET'  && $action === 'config'           => apiConfig(),
    $method === 'POST' && $action === 'create'           => apiCreate($userId),
    $method === 'POST' && $action === 'update'           => apiUpdate($userId),
    $method === 'POST' && $action === 'delete'           => apiDelete($userId),
    $method === 'POST' && $action === 'snooze'           => apiSnooze($userId),
    $method === 'POST' && $action === 'done'             => apiDone($userId),
    $method === 'POST' && $action === 'subscribe_push'   => apiSubscribePush($userId),
    $method === 'POST' && $action === 'unsubscribe_push' => apiUnsubscribePush($userId),
    $method === 'POST' && $action === 'prefs_save'       => apiPrefsSave($userId),
    default => jsonOut(['error' => 'Unknown action'], 400),
};

function apiConfig(): never {
    jsonOut(['vapidPublicKey' => VAPID_PUBLIC_KEY]);
}

function apiList(int $userId): never {
    $month  = $_GET['month']  ?? null;
    $status = $_GET['status'] ?? null;

    $sql    = 'SELECT * FROM reminders WHERE user_id = ?';
    $params = [$userId];

    if ($month) {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) jsonOut(['error' => 'invalid month format'], 400);
        $sql     .= ' AND remind_at BETWEEN ? AND ?';
        $params[] = $month . '-01 00:00:00';
        $params[] = date('Y-m-t 23:59:59', strtotime($month . '-01'));
    }
    if ($status) {
        $sql     .= ' AND status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY remind_at ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    jsonOut($stmt->fetchAll());
}

function apiDue(int $userId): never {
    $stmt = db()->prepare("
        SELECT * FROM reminders
        WHERE user_id = ?
          AND status IN ('active','snoozed')
          AND (
            (status = 'active'  AND remind_at    <= NOW())
            OR
            (status = 'snoozed' AND snoozed_until <= NOW())
          )
        ORDER BY remind_at ASC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    jsonOut($stmt->fetchAll());
}

function apiCreate(int $userId): never {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    $title = trim($d['title'] ?? '');
    if ($title === '') jsonOut(['error' => 'title required'], 400);

    $remindAt = $d['remind_at'] ?? '';
    if (!$remindAt || !strtotime($remindAt)) jsonOut(['error' => 'invalid remind_at'], 400);

    $recurrence = in_array($d['recurrence'] ?? '', ['none','daily','weekly','monthly','yearly'])
        ? $d['recurrence'] : 'none';

    $color = null;
    if (!empty($d['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $d['color'])) {
        $color = $d['color'];
    }

    $stmt = db()->prepare('
        INSERT INTO reminders
            (user_id, title, description, color, remind_at, recurrence, recurrence_end, email_notify, email_lead_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $userId,
        $title,
        !empty($d['description']) ? $d['description'] : null,
        $color,
        date('Y-m-d H:i:s', strtotime($remindAt)),
        $recurrence,
        !empty($d['recurrence_end']) && strtotime($d['recurrence_end']) ? $d['recurrence_end'] : null,
        !empty($d['email_notify']) ? 1 : 0,
        isset($d['email_lead_time']) && $d['email_lead_time'] !== '' ? (int)$d['email_lead_time'] : null,
    ]);
    $id   = (int)db()->lastInsertId();
    $stmt = db()->prepare('SELECT * FROM reminders WHERE id = ?');
    $stmt->execute([$id]);
    jsonOut($stmt->fetch(), 201);
}

function apiUpdate(int $userId): never {
    $d  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonOut(['error' => 'id required'], 400);

    $stmt = db()->prepare('SELECT id FROM reminders WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) jsonOut(['error' => 'Not found'], 404);

    $allowed = ['title','description','color','remind_at','recurrence','recurrence_end',
                'email_notify','email_lead_time','status'];
    $fields  = [];
    $params  = [];
    foreach ($allowed as $f) {
        if (!array_key_exists($f, $d)) continue;
        $v = $d[$f];
        if ($f === 'remind_at' && $v !== '' && !strtotime($v)) jsonOut(['error' => 'invalid remind_at'], 400);
        if ($f === 'recurrence_end' && $v !== '' && !strtotime((string)$v)) jsonOut(['error' => 'invalid recurrence_end'], 400);
        if ($f === 'status' && !in_array($v, ['active','done','snoozed'])) jsonOut(['error' => 'invalid status'], 400);
        if ($f === 'email_lead_time' && $v !== '' && $v !== null && !is_numeric($v)) jsonOut(['error' => 'invalid email_lead_time'], 400);
        if ($f === 'color' && $v !== null && $v !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v)) jsonOut(['error' => 'invalid color'], 400);
        $fields[] = "$f = ?";
        $params[] = ($v === '') ? null : $v;
    }
    if (empty($fields)) jsonOut(['error' => 'nothing to update'], 400);

    $params[] = $id;
    db()->prepare('UPDATE reminders SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

    $stmt = db()->prepare('SELECT * FROM reminders WHERE id = ?');
    $stmt->execute([$id]);
    jsonOut($stmt->fetch());
}

function apiDelete(int $userId): never {
    $d  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonOut(['error' => 'id required'], 400);
    db()->prepare('DELETE FROM reminders WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    jsonOut(['ok' => true]);
}

function apiDone(int $userId): never {
    $d  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonOut(['error' => 'id required'], 400);
    db()->prepare("UPDATE reminders SET status = 'done' WHERE id = ? AND user_id = ?")
        ->execute([$id, $userId]);
    jsonOut(['ok' => true]);
}

function apiSnooze(int $userId): never {
    $d       = json_decode(file_get_contents('php://input'), true) ?? [];
    $id      = (int)($d['id'] ?? 0);
    if (!$id) jsonOut(['error' => 'id required'], 400);
    $minutes = (int)($d['minutes'] ?? 10);
    if (!in_array($minutes, [10, 60])) jsonOut(['error' => 'invalid snooze duration'], 400);

    $until = date('Y-m-d H:i:s', time() + $minutes * 60);
    db()->prepare("UPDATE reminders SET status = 'snoozed', snoozed_until = ? WHERE id = ? AND user_id = ?")
        ->execute([$until, $id, $userId]);
    jsonOut(['ok' => true, 'snoozed_until' => $until]);
}

function apiSubscribePush(int $userId): never {
    $d        = json_decode(file_get_contents('php://input'), true) ?? [];
    $endpoint = $d['endpoint'] ?? '';
    $p256dh   = $d['keys']['p256dh'] ?? '';
    $auth     = $d['keys']['auth'] ?? '';
    if (!$endpoint || !$p256dh || !$auth) jsonOut(['error' => 'invalid subscription'], 400);

    db()->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')->execute([$endpoint]);
    db()->prepare('INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?,?,?,?)')
        ->execute([$userId, $endpoint, $p256dh, $auth]);
    jsonOut(['ok' => true]);
}

function apiUnsubscribePush(int $userId): never {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    db()->prepare('DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?')
        ->execute([$d['endpoint'] ?? '', $userId]);
    jsonOut(['ok' => true]);
}

function apiPrefsGet(int $userId): never {
    $stmt = db()->prepare('SELECT * FROM reminder_user_prefs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch() ?: [
        'user_id'            => $userId,
        'default_email_lead' => 1440,
        'email_override'     => null,
        'email_cache'        => null,
        'theme'              => null,
        'cal_view'           => 'compact',
    ];
    unset($prefs['email_cache']);
    jsonOut($prefs);
}

function apiPrefsSave(int $userId): never {
    $d       = json_decode(file_get_contents('php://input'), true) ?? [];
    $lead    = isset($d['default_email_lead']) ? max(5, (int)$d['default_email_lead']) : 1440;
    $email   = !empty($d['email_override']) ? trim($d['email_override']) : null;
    $theme   = in_array($d['theme'] ?? '', ['dark','light']) ? $d['theme'] : null;
    $calView = in_array($d['cal_view'] ?? '', ['compact','full','list']) ? $d['cal_view'] : null;

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['error' => 'invalid email'], 400);
    }

    $fields = 'user_id, default_email_lead, email_override, theme';
    $vals   = '?, ?, ?, ?';
    $update = 'default_email_lead = VALUES(default_email_lead),
               email_override = VALUES(email_override),
               theme = VALUES(theme)';
    $params = [$userId, $lead, $email, $theme];

    if ($calView !== null) {
        $fields .= ', cal_view';
        $vals   .= ', ?';
        $update .= ', cal_view = VALUES(cal_view)';
        $params[] = $calView;
    }

    db()->prepare("
        INSERT INTO reminder_user_prefs ($fields)
        VALUES ($vals)
        ON DUPLICATE KEY UPDATE $update
    ")->execute($params);
    jsonOut(['ok' => true]);
}
