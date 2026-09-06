<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

$user   = requireAuth();
$userId = (int)$user['id'];

$getAction = $_GET['action'] ?? '';

// CSV-Vorlage Download
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $getAction === 'csv_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reminder-vorlage.csv"');
    echo "title,datetime,recurrence,description,color\n";
    echo "Beispiel-Termin,\"2026-05-10 14:00\",none,Beschreibung optional,#3498db\n";
    echo "Geburtstag,\"2026-06-15 09:00\",yearly,,\n";
    exit;
}

// CSV-Export
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $getAction === 'export_csv') {
    $stmt = db()->prepare('SELECT title, remind_at, recurrence, description, color FROM reminders WHERE user_id = ? AND status != ? ORDER BY remind_at ASC');
    $stmt->execute([$userId, 'done']);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reminder-export-' . date('Y-m-d') . '.csv"');
    echo "title,datetime,recurrence,description,color\n";
    foreach ($rows as $r) {
        $dt = date('Y-m-d H:i', strtotime($r['remind_at']));
        echo csvField($r['title']) . ',' . csvField($dt) . ',' . csvField($r['recurrence']) . ','
           . csvField($r['description'] ?? '') . ',' . csvField($r['color'] ?? '') . "\n";
    }
    exit;
}

// ICS-Export
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $getAction === 'export_ics') {
    $stmt = db()->prepare('SELECT * FROM reminders WHERE user_id = ? AND status != ? ORDER BY remind_at ASC');
    $stmt->execute([$userId, 'done']);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="reminder-export-' . date('Y-m-d') . '.ics"');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Schnüddels Reminder//DE\r\nCALSCALE:GREGORIAN\r\n";
    foreach ($rows as $r) {
        $ts      = strtotime($r['remind_at']);
        $dtstart = date('Ymd\THis', $ts);
        $uid     = 'reminder-' . $r['id'] . '@reminder.schnueddels.de';
        echo "BEGIN:VEVENT\r\n";
        echo "UID:$uid\r\n";
        echo "DTSTART:$dtstart\r\n";
        echo "SUMMARY:" . icsEscape($r['title']) . "\r\n";
        if (!empty($r['description'])) echo "DESCRIPTION:" . icsEscape($r['description']) . "\r\n";
        if ($r['recurrence'] !== 'none') {
            $freq = strtoupper($r['recurrence']);
            $rrule = "FREQ=$freq";
            if (!empty($r['recurrence_end'])) $rrule .= ';UNTIL=' . date('Ymd', strtotime($r['recurrence_end'])) . 'T000000Z';
            echo "RRULE:$rrule\r\n";
        }
        echo "END:VEVENT\r\n";
    }
    echo "END:VCALENDAR\r\n";
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Preview (multipart/form-data)
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $name = $file['name'];
        $tmp  = $file['tmp_name'];
        $size = $file['size'];

        if ($size > 2 * 1024 * 1024) jsonOut(['error' => 'Datei zu groß (max. 2 MB)'], 400);

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['ics', 'csv'])) jsonOut(['error' => 'Nur .ics und .csv erlaubt'], 400);

        $content = file_get_contents($tmp);
        $items   = $ext === 'ics' ? parseICS($content) : parseCSV($content);

        $skipped = 0;
        $clean   = [];
        foreach ($items as $item) {
            if (isDuplicate($userId, $item['title'], $item['remind_at'])) {
                $skipped++;
            } else {
                $clean[] = $item;
            }
        }

        jsonOut(['items' => $clean, 'total' => count($items), 'skipped_preview' => $skipped, 'format' => $ext]);
    }

    // Confirm import (JSON body)
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (($body['action'] ?? '') === 'confirm') {
        $items    = $body['items'] ?? [];
        $imported = 0;
        $skipped  = 0;

        $stmt = db()->prepare('
            INSERT INTO reminders (user_id, title, description, color, remind_at, recurrence, recurrence_end)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($items as $item) {
            if (isDuplicate($userId, $item['title'], $item['remind_at'])) { $skipped++; continue; }
            $colorVal = !empty($item['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $item['color'])
                ? $item['color'] : null;
            $stmt->execute([
                $userId,
                $item['title'],
                $item['description'] ?? null,
                $colorVal,
                $item['remind_at'],
                $item['recurrence'] ?? 'none',
                $item['recurrence_end'] ?? null,
            ]);
            $imported++;
        }

        $fmt = in_array($body['format'] ?? '', ['ics', 'csv']) ? $body['format'] : 'csv';
        db()->prepare('INSERT INTO import_log (user_id, filename, format, imported, skipped) VALUES (?,?,?,?,?)')
            ->execute([$userId, 'import', $fmt, $imported, $skipped]);

        jsonOut(['imported' => $imported, 'skipped' => $skipped]);
    }
}

jsonOut(['error' => 'Bad request'], 400);

// ── ICS Parser ────────────────────────────────────────────
function parseICS(string $content): array {
    $items   = [];
    $inEvent = false;
    $cur     = [];

    foreach (explode("\n", $content) as $raw) {
        $line = rtrim($raw, "\r");

        if ($line === 'BEGIN:VEVENT')  { $inEvent = true; $cur = []; continue; }
        if ($line === 'END:VEVENT')    { $inEvent = false; $item = buildICSItem($cur); if ($item) $items[] = $item; continue; }
        if (!$inEvent) continue;

        if (str_starts_with($line, 'DTSTART'))     $cur['dtstart']     = extractICSVal($line);
        if (str_starts_with($line, 'SUMMARY'))     $cur['summary']     = extractICSVal($line);
        if (str_starts_with($line, 'DESCRIPTION')) $cur['description'] = extractICSVal($line);
        if (str_starts_with($line, 'RRULE'))       $cur['rrule']       = extractICSVal($line);
    }
    return $items;
}

function extractICSVal(string $line): string {
    $pos = strpos($line, ':');
    return $pos !== false ? trim(substr($line, $pos + 1)) : '';
}

function icsUnescape(string $v): string {
    // ICS-Spec: \n → Zeilenumbruch, \\ → \, \, → ,, \; → ;
    $v = str_replace(['\\n', '\\N', '\\\\', '\\,', '\\;'], ["\n", "\n", '\\', ',', ';'], $v);
    return $v;
}

function buildICSItem(array $cur): ?array {
    if (empty($cur['dtstart']) || empty($cur['summary'])) return null;

    $ds = $cur['dtstart'];
    $ts = null;
    if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})/', $ds, $m)) {
        $ts = mktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
    } elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $ds, $m)) {
        $ts = mktime(9, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
    }
    if (!$ts) return null;

    $recurrence    = 'none';
    $recurrenceEnd = null;
    if (!empty($cur['rrule'])) {
        $rr = [];
        foreach (explode(';', $cur['rrule']) as $part) {
            [$k, $v] = explode('=', $part, 2) + ['', ''];
            $rr[trim($k)] = trim($v);
        }
        $recurrence = match(strtoupper($rr['FREQ'] ?? '')) {
            'DAILY'   => 'daily',
            'WEEKLY'  => 'weekly',
            'MONTHLY' => 'monthly',
            'YEARLY'  => 'yearly',
            default   => 'none',
        };
        if (!empty($rr['UNTIL']) && preg_match('/^(\d{4})(\d{2})(\d{2})/', $rr['UNTIL'], $m)) {
            $recurrenceEnd = $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        // BYDAY/BYMONTHDAY nicht unterstützt → keine Wiederholung
        if (isset($rr['BYDAY']) || isset($rr['BYMONTHDAY'])) {
            $recurrence    = 'none';
            $recurrenceEnd = null;
        }
    }

    return [
        'title'          => icsUnescape(html_entity_decode($cur['summary'])),
        'remind_at'      => date('Y-m-d H:i:s', $ts),
        'description'    => !empty($cur['description']) ? icsUnescape(html_entity_decode($cur['description'])) : null,
        'recurrence'     => $recurrence,
        'recurrence_end' => $recurrenceEnd,
    ];
}

// ── CSV Parser ────────────────────────────────────────────
function parseCSV(string $content): array {
    // Strip UTF-8 BOM (Excel/Windows often adds \xEF\xBB\xBF)
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }

    $lines = array_filter(explode("\n", $content), fn($l) => trim($l) !== '');
    if (empty($lines)) return [];

    $header   = str_getcsv(array_shift($lines));
    $header   = array_map('trim', $header);
    $titleIdx = array_search('title', $header);
    $dtIdx    = array_search('datetime', $header);
    $recIdx   = array_search('recurrence', $header);
    $descIdx  = array_search('description', $header);
    $colorIdx = array_search('color', $header);

    if ($titleIdx === false || $dtIdx === false) return [];

    $items = [];
    foreach ($lines as $line) {
        $row   = array_map('trim', str_getcsv($line));
        $title = $row[$titleIdx] ?? '';
        $dt    = $row[$dtIdx]    ?? '';
        if (!$title || !$dt) continue;

        $ts = strtotime($dt);
        if (!$ts) continue;

        $rec = ($recIdx !== false && isset($row[$recIdx])) ? $row[$recIdx] : 'none';
        if (!in_array($rec, ['none','daily','weekly','monthly','yearly'])) $rec = 'none';

        $desc = ($descIdx !== false && isset($row[$descIdx]) && $row[$descIdx] !== '')
            ? $row[$descIdx]
            : null;

        $rawColor = ($colorIdx !== false && isset($row[$colorIdx])) ? $row[$colorIdx] : '';
        $color    = preg_match('/^#[0-9a-fA-F]{6}$/', $rawColor) ? $rawColor : null;

        $items[] = [
            'title'          => $title,
            'remind_at'      => date('Y-m-d H:i:s', $ts),
            'description'    => $desc,
            'recurrence'     => $rec,
            'recurrence_end' => null,
            'color'          => $color,
        ];
    }
    return $items;
}

function isDuplicate(int $userId, string $title, string $remindAt): bool {
    $stmt = db()->prepare('SELECT id FROM reminders WHERE user_id = ? AND title = ? AND remind_at = ?');
    $stmt->execute([$userId, $title, $remindAt]);
    return (bool)$stmt->fetch();
}

function csvField(string $v): string {
    if (str_contains($v, ',') || str_contains($v, '"') || str_contains($v, "\n")) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}

function icsEscape(string $v): string {
    return str_replace(["\r\n", "\n", "\r", ',', ';', '\\'], ['\\n', '\\n', '\\n', '\\,', '\\;', '\\\\'], $v);
}
