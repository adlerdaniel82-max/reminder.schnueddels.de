<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
$user  = requireAuth();
$prefs = (function() use ($user) {
    $stmt = db()->prepare('SELECT theme FROM reminder_user_prefs WHERE user_id = ?');
    $stmt->execute([(int)$user['id']]);
    return $stmt->fetch() ?: ['theme' => null];
})();
$theme        = $prefs['theme'] ?? 'dark';
$themeVersion = '20260407a';
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reminder – Schnüddels</title>
<link rel="stylesheet" href="https://schnueddels.de/assets/css/schnueddels-ui.css?v=20260503d" data-sd-ui-css="true">
<link id="themeStylesheet" rel="stylesheet" href="/assets/css/themes/<?= $theme ?>.css?v=<?= $themeVersion ?>">
<script defer src="https://schnueddels.de/assets/js/schnueddels-header.js?v=20260503d" data-project="Reminder"></script>
<style>
:root { --font: var(--sd-font-sans, system-ui, -apple-system, sans-serif); }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: var(--font);
  background:
    radial-gradient(circle at top left, color-mix(in srgb, var(--sd-accent, #4a90e2) 10%, transparent), transparent 30%),
    linear-gradient(180deg, color-mix(in srgb, var(--sd-bg, #f0f2f5) 92%, #fff 8%), var(--bg));
  color: var(--ink);
  min-height: 100dvh;
  padding: 14px 14px 20px;
}

/* ── Header ── */
.header {
  position: sticky;
  top: 12px;
  z-index: 20;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 12px;
  max-width: 1180px;
  margin: 0 auto 14px;
  padding:16px 18px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--sd-panel, var(--card)) 90%, transparent), var(--card));
  border:1px solid var(--card-border);
  border-radius: 20px;
  box-shadow: var(--shadow);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}
.header-brand { font-weight:800; font-size:1.1rem; letter-spacing: .01em; }
.header-nav   { display:flex; gap:8px; flex-wrap: wrap; }
.nav-btn      { padding:9px 15px; border-radius:999px; border:1px solid var(--card-border);
               background:transparent; color:var(--ink); cursor:pointer; font-size:.85rem; font-weight:700; }
.nav-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }

/* ── Views ── */
.view {
  display:none;
  padding: 0;
  max-width: 1180px;
  margin: 0 auto 14px;
}
.view.active { display:block; }
.view > * {
  background: var(--sd-panel, var(--card));
  border: 1px solid var(--card-border);
  border-radius: 20px;
  box-shadow: var(--shadow);
  padding: 20px;
}

/* ── Calendar ── */
.cal-header  { display:flex; align-items:center; justify-content:space-between; gap: 12px; margin-bottom:16px; }
.cal-title   { font-weight:800; font-size:1.1rem; }
.cal-nav-btn { background:transparent; border:1px solid var(--card-border); border-radius:999px;
              padding:8px 13px; cursor:pointer; color:var(--ink); }
.cal-grid    { display:grid; grid-template-columns:repeat(7,1fr); gap:8px; }
.cal-day-hdr { text-align:center; font-size:.78rem; font-weight:700; color:var(--muted); padding:6px 0; }
.cal-cell    { min-height:76px; border-radius:14px; border:1px solid var(--card-border);
              background:color-mix(in srgb, var(--card) 92%, transparent); padding:10px; cursor:pointer; }
.cal-cell:hover { background:color-mix(in srgb, var(--accent) 12%, var(--card)); transform: translateY(-1px); }
.cal-cell.other-month { opacity:.4; }
.cal-cell.today { border-color:var(--primary); }
.cal-day-num { font-size:.8rem; font-weight:700; }
.cal-badge   { display:inline-flex; align-items:center; justify-content:center;
              background:var(--primary); color:#fff; border-radius:99px;
              font-size:.7rem; font-weight:700; padding:1px 6px; margin-top:4px; min-width:20px; }
.cal-upcoming { margin-top:20px; }
.cal-upcoming-title { font-size:.85rem; font-weight:700; color:var(--muted); margin-bottom:8px; }
.cal-upcoming-row   { display:flex; align-items:center; gap:10px; padding:9px 0;
                      border-bottom:1px solid var(--card-border); cursor:pointer; }
.cal-upcoming-row:last-child { border-bottom:none; }
.cal-upcoming-dt    { font-size:.8rem; color:var(--muted); min-width:115px; flex-shrink:0; }
.cal-upcoming-ttl   { font-weight:600; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

/* ── Segment-Control ── */
.cal-view-seg { display:flex; margin-bottom:12px; gap: 8px; }
.seg-btn { flex:1; padding:9px 4px; border:1px solid var(--card-border); background:transparent;
           color:var(--ink); cursor:pointer; font-size:.82rem; font-weight:700; transition:background .15s; border-radius:999px; }
.seg-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }

/* ── Kalender Dots (Kompakt) ── */
.cal-dots { display:flex; flex-wrap:wrap; margin-top:4px; gap:2px; }
.cal-dot  { display:inline-block; width:10px; height:10px; border-radius:50%; }

/* ── Kalender Chips (Voll) ── */
.cal-grid.mode-full .cal-cell { min-height:90px; }
.cal-chips { display:flex; flex-direction:column; gap:2px; margin-top:3px; }
.cal-chip  { font-size:.7rem; font-weight:600; border-radius:4px;
             padding:1px 5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
             cursor:pointer; }
.cal-chip-more { font-size:.68rem; color:var(--muted); padding:1px 2px; }

/* ── Listen-Modus ── */
#cal-list { display:none; }
.list-day { margin-bottom:6px; }
.list-day-hdr { font-size:.82rem; font-weight:800; padding:8px 0 4px;
                border-bottom:1px solid var(--card-border); cursor:pointer;
                color:var(--muted); }
.list-day-hdr.today { color:var(--primary); }
.list-day-empty { font-size:.82rem; color:var(--muted); padding:6px 0 6px 4px; cursor:pointer; }
.list-rem-row { display:flex; align-items:center; gap:8px; padding:6px 4px;
                border-bottom:1px solid var(--card-border); cursor:pointer; }
.list-rem-row:last-child { border-bottom:none; }
.list-rem-dot  { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.list-rem-time { font-size:.8rem; color:var(--muted); min-width:38px; flex-shrink:0; }
.list-rem-title{ font-size:.88rem; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

/* ── Farbwähler ── */
.color-swatches { display:flex; flex-wrap:wrap; gap:8px; margin-top:4px; }
.color-swatch   { width:26px; height:26px; border-radius:50%; border:2px solid transparent;
                  cursor:pointer; padding:0; transition:transform .1s; }
.color-swatch:hover  { transform:scale(1.15); }
.color-swatch.active { outline:2px solid var(--ink); outline-offset:2px; }
.color-swatch-none   { background:transparent !important; border:2px solid var(--card-border) !important;
                        font-size:.8rem; color:var(--muted); display:flex; align-items:center;
                        justify-content:center; line-height:1; }

/* ── List view ── */
.reminder-list { display:flex; flex-direction:column; gap:10px; }
.reminder-card { background:var(--card); border:1px solid var(--card-border); border-radius:16px;
                padding:16px 18px; display:flex; gap:12px; align-items:flex-start; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
.reminder-card.done   { opacity:.5; }
.reminder-card.snoozed{ border-color:var(--warning, #f9e2af); }
.reminder-info { flex:1; min-width:0; }
.reminder-title { font-weight:700; margin-bottom:4px; }
.reminder-meta  { font-size:.82rem; color:var(--muted); }
.reminder-actions { display:flex; gap:6px; }
.btn-icon { border:1px solid var(--card-border); background:transparent; color:var(--ink);
           border-radius:999px; padding:7px 12px; cursor:pointer; font-size:.82rem; }
.btn-icon.danger { color:var(--danger); border-color:var(--danger); }

/* ── FAB ── */
.fab { position:fixed; bottom:24px; right:24px; width:56px; height:56px; border-radius:50%;
      background:var(--primary); color:#fff; border:none; font-size:1.6rem; cursor:pointer;
      box-shadow:0 8px 22px rgba(0,0,0,.22); display:flex; align-items:center; justify-content:center; }

/* ── Modal ── */
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000;
                 display:flex; align-items:center; justify-content:center; padding:16px; }
.modal-backdrop.hidden { display:none; }
.modal { background:var(--card); border-radius:20px; padding:24px; width:100%; max-width:500px;
        max-height:90dvh; overflow-y:auto; border: 1px solid var(--card-border); box-shadow: var(--shadow); }
.modal-title { font-weight:800; font-size:1.1rem; margin-bottom:20px; }
.field  { margin-bottom:14px; }
.label  { display:block; font-size:.85rem; font-weight:600; margin-bottom:6px; color:var(--muted); }
.input, .select, .textarea {
  width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--card-border);
  background:var(--bg); color:var(--ink); font-size:.9rem; }
.textarea { min-height:80px; resize:vertical; }
.input:focus, .select:focus, .textarea:focus { outline:none; border-color:var(--primary); }
.checkbox-row { display:flex; align-items:center; gap:8px; font-size:.9rem; }
.modal-footer { display:flex; justify-content:space-between; gap:8px; margin-top:20px; }
.btn-primary  { padding:10px 20px; border-radius:10px; background:var(--primary); color:#fff;
               border:none; cursor:pointer; font-weight:700; font-size:.9rem; }
.btn-secondary{ padding:10px 20px; border-radius:10px; background:transparent;
               color:var(--ink); border:1px solid var(--card-border); cursor:pointer; font-size:.9rem; }
.btn-danger   { padding:10px 20px; border-radius:10px; background:var(--danger); color:#fff;
               border:none; cursor:pointer; font-size:.9rem; }

/* ── Settings ── */
.settings-card { background:var(--card); border:1px solid var(--card-border); border-radius:18px;
                padding:20px; margin-bottom:16px; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
.settings-title{ font-weight:700; margin-bottom:14px; }

/* ── Import ── */
.dropzone { border:2px dashed var(--card-border); border-radius:18px; padding:40px; text-align:center;
           cursor:pointer; transition:border-color .2s, transform .18s ease; margin-bottom:16px; background: color-mix(in srgb, var(--card) 95%, transparent); }
.dropzone:hover { transform: translateY(-1px); }
.dropzone.drag-over { border-color:var(--primary); }
.dropzone input { display:none; }
.import-table   { width:100%; border-collapse:collapse; font-size:.85rem; }
.import-table th, .import-table td { padding:8px 10px; text-align:left;
  border-bottom:1px solid var(--card-border); }
.import-table th { font-weight:700; color:var(--muted); }

/* ── Mobile Bottom Nav ── */
.mobile-nav { display:none; position:fixed; bottom:0; left:0; right:0;
              background:var(--card); border-top:1px solid var(--card-border);
              z-index:200; box-shadow: 0 -8px 24px rgba(0,0,0,.08); }
.mobile-nav-btn { flex:1; padding:10px 4px 8px; border:none; background:transparent;
                  color:var(--muted); cursor:pointer; font-size:.65rem; font-weight:600;
                  display:flex; flex-direction:column; align-items:center; gap:3px; line-height:1; }
.mobile-nav-btn .mn-icon { font-size:1.2rem; }
.mobile-nav-btn.active { color:var(--primary); }

@media (max-width:600px) {
  body { padding: 10px 10px 20px; }
  .header { top: 8px; margin-bottom: 12px; padding: 14px 14px; }
  .header-nav  { display:none; }
  .cal-cell    { min-height:44px; }
  .mobile-nav  { display:flex; }
  .view        { padding-bottom:72px; }
  .fab         { bottom:72px; }
}

/* Phase 2: Schnueddels component bridge */
.header,
.settings-card,
.reminder-card,
.cal-cell,
.modal,
.dropzone,
.import-table,
.mobile-nav {
  border-color: var(--sd-border, var(--card-border));
  background: var(--sd-panel, var(--card));
  box-shadow: var(--sd-shadow-sm, none);
}

.header {
  background: color-mix(in srgb, var(--sd-bg-raised, var(--card)) 82%, transparent);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}

.nav-btn,
.cal-nav-btn,
.seg-btn,
.btn-icon,
.btn-primary,
.btn-secondary,
.btn-danger,
.mobile-nav-btn {
  border-radius: var(--sd-radius-md, 10px);
  font-weight: 700;
}

.btn-primary,
.nav-btn.active,
.seg-btn.active {
  background: var(--sd-accent, var(--primary));
  color: var(--sd-accent-ink, #fff);
  border-color: var(--sd-accent, var(--primary));
}

.btn-secondary,
.nav-btn,
.cal-nav-btn,
.seg-btn,
.btn-icon {
  border-color: var(--sd-border, var(--card-border));
  background: var(--sd-surface, transparent);
  color: var(--sd-text, var(--ink));
}

.btn-danger,
.btn-icon.danger {
  border-color: color-mix(in srgb, var(--sd-danger, var(--danger)) 52%, transparent);
  background: color-mix(in srgb, var(--sd-danger, var(--danger)) 14%, transparent);
  color: var(--sd-danger, var(--danger));
}

.input,
.select,
.textarea {
  border-color: var(--sd-border, var(--card-border));
  border-radius: var(--sd-radius-md, 10px);
  background: var(--sd-surface, var(--bg));
  color: var(--sd-text, var(--ink));
}

.input:focus,
.select:focus,
.textarea:focus,
.btn-primary:focus-visible,
.btn-secondary:focus-visible,
.btn-danger:focus-visible,
.nav-btn:focus-visible,
.cal-nav-btn:focus-visible,
.seg-btn:focus-visible,
.btn-icon:focus-visible {
  outline: none;
  box-shadow: var(--sd-focus, none);
}

.modal-backdrop {
  background: rgba(0, 0, 0, 0.58);
}
</style>
</head>
<body class="sd-app reminder-app">

<header class="header sd-toolbar">
  <span class="header-brand">🔔 Reminder</span>
  <nav class="header-nav">
    <button class="nav-btn sd-button active" data-view="calendar" onclick="showView('calendar')">Kalender</button>
    <button class="nav-btn sd-button"        data-view="import"   onclick="showView('import')">Import</button>
    <button class="nav-btn sd-button"        data-view="settings" onclick="showView('settings')">Einstellungen</button>
  </nav>
</header>

<!-- Kalender-View -->
<section id="view-calendar" class="view active">
  <div class="cal-header">
    <button class="cal-nav-btn" onclick="calPrev()">◀</button>
    <span class="cal-title" id="cal-title"></span>
    <button class="cal-nav-btn" onclick="calNext()">▶</button>
  </div>
  <div class="cal-view-seg">
    <button class="seg-btn active" data-mode="compact" onclick="setCalView('compact')">Kompakt</button>
    <button class="seg-btn"        data-mode="full"    onclick="setCalView('full')">Voll</button>
    <button class="seg-btn"        data-mode="list"    onclick="setCalView('list')">Liste</button>
  </div>
  <div class="cal-grid" id="cal-grid"></div>
  <div id="cal-list"></div>
  <div id="cal-upcoming"></div>
</section>

<!-- Listen-View -->
<section id="view-list" class="view">
  <div id="list-back-bar" style="display:none;margin-bottom:12px">
    <button class="btn-secondary" onclick="goBackToCalendar()">← Kalender</button>
  </div>
  <div class="reminder-list" id="reminder-list"></div>
</section>

<!-- Import-View -->
<section id="view-import" class="view">
  <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
    <a class="btn-secondary" href="/import.php?action=export_csv" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px">⬇ Export CSV</a>
    <a class="btn-secondary" href="/import.php?action=export_ics" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px">⬇ Export ICS</a>
  </div>
  <div class="dropzone" id="dropzone" onclick="document.getElementById('file-input').click()">
    <div>📎 ICS oder CSV hierher ziehen oder klicken</div>
    <input type="file" id="file-input" accept=".ics,.csv">
  </div>
  <div id="import-preview" style="display:none">
    <table class="import-table">
      <thead><tr><th>Titel</th><th>Datum/Zeit</th><th>Wiederholung</th></tr></thead>
      <tbody id="import-tbody"></tbody>
    </table>
    <p id="import-summary" style="margin:10px 0;font-size:.85rem;color:var(--muted)"></p>
    <button class="btn-primary" onclick="confirmImport()">Import bestätigen</button>
    <button class="btn-secondary" onclick="resetImport()" style="margin-left:8px">Abbrechen</button>
  </div>
  <div id="import-result" style="display:none;padding:16px;background:var(--card);border-radius:12px"></div>
  <div style="margin-top:24px">
    <strong style="font-size:.9rem">CSV-Format:</strong>
    <code style="display:block;margin-top:8px;padding:12px;background:var(--card);border-radius:8px;font-size:.82rem">
      title,datetime,recurrence,description<br>
      Arzttermin,"2026-05-10 14:00",none,Dr. Müller<br>
      Geburtstag Mama,"2026-06-15 09:00",yearly,
    </code>
    <a href="/import.php?action=csv_template" style="font-size:.85rem;color:var(--primary)">Vorlage herunterladen</a>
  </div>
</section>

<!-- Einstellungen-View -->
<section id="view-settings" class="view">
  <div class="settings-card">
    <div class="settings-title">E-Mail-Erinnerung</div>
    <div class="field">
      <label class="label">Standard-Vorlaufzeit</label>
      <select class="select" id="pref-lead">
        <option value="5">5 Minuten</option>
        <option value="30">30 Minuten</option>
        <option value="60">1 Stunde</option>
        <option value="180">3 Stunden</option>
        <option value="1440" selected>1 Tag</option>
        <option value="10080">1 Woche</option>
      </select>
    </div>
    <div class="field">
      <label class="label">Alternative E-Mail-Adresse (optional)</label>
      <input class="input" type="email" id="pref-email" placeholder="meine@mail.de">
    </div>
  </div>
  <div class="settings-card">
    <div class="settings-title">Push-Benachrichtigungen</div>
    <div id="push-status" style="font-size:.9rem;color:var(--muted);margin-bottom:12px">Wird geprüft...</div>
    <button class="btn-secondary" id="push-btn" onclick="togglePush()">Aktivieren</button>
  </div>
  <button class="btn-primary" onclick="savePrefs()">Einstellungen speichern</button>
</section>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-nav" id="mobile-nav">
  <button class="mobile-nav-btn active" data-view="calendar" onclick="showView('calendar')">
    <span class="mn-icon">📅</span><span>Kalender</span>
  </button>
  <button class="mobile-nav-btn" data-view="import" onclick="showView('import')">
    <span class="mn-icon">📥</span><span>Import</span>
  </button>
  <button class="mobile-nav-btn" data-view="settings" onclick="showView('settings')">
    <span class="mn-icon">⚙️</span><span>Einstellungen</span>
  </button>
</nav>

<!-- FAB -->
<button class="fab" onclick="openDialog(null)" title="Neuer Reminder">+</button>

<!-- Reminder-Modal -->
<div class="modal-backdrop hidden" id="modal">
  <div class="modal">
    <div class="modal-title" id="modal-title">Neuer Reminder</div>
    <input type="hidden" id="rm-id">
    <div class="field">
      <label class="label">Titel *</label>
      <input class="input" id="rm-title" placeholder="Was soll erinnert werden?">
    </div>
    <div class="field">
      <label class="label">Datum &amp; Uhrzeit *</label>
      <input class="input" type="datetime-local" id="rm-datetime">
    </div>
    <div class="field">
      <label class="label">Beschreibung</label>
      <textarea class="textarea" id="rm-desc" placeholder="Optional..."></textarea>
    </div>
    <div class="field">
      <label class="label">Farbe</label>
      <div class="color-swatches" id="color-swatches"></div>
    </div>
    <div class="field">
      <label class="label">Wiederholung</label>
      <select class="select" id="rm-recurrence" onchange="toggleRecurrenceEnd()">
        <option value="none">Keine</option>
        <option value="daily">Täglich</option>
        <option value="weekly">Wöchentlich</option>
        <option value="monthly">Monatlich</option>
        <option value="yearly">Jährlich</option>
      </select>
    </div>
    <div class="field" id="recurrence-end-wrap" style="display:none">
      <label class="label">Wiederholen bis</label>
      <input class="input" type="date" id="rm-recurrence-end">
    </div>
    <div class="field">
      <div class="checkbox-row">
        <input type="checkbox" id="rm-email-notify" onchange="toggleEmailLead()">
        <label for="rm-email-notify">E-Mail-Erinnerung senden</label>
      </div>
    </div>
    <div class="field" id="email-lead-wrap" style="display:none">
      <label class="label">Vorlaufzeit</label>
      <select class="select" id="rm-email-lead">
        <option value="">Standard (aus Einstellungen)</option>
        <option value="5">5 Minuten</option>
        <option value="30">30 Minuten</option>
        <option value="60">1 Stunde</option>
        <option value="180">3 Stunden</option>
        <option value="1440">1 Tag</option>
        <option value="10080">1 Woche</option>
      </select>
    </div>
    <div class="modal-footer">
      <div>
        <button class="btn-primary" onclick="saveReminder()">Speichern</button>
        <button class="btn-secondary" onclick="closeDialog()" style="margin-left:8px">Abbrechen</button>
      </div>
      <button class="btn-danger" id="delete-btn" onclick="deleteReminder()" style="display:none">Löschen</button>
    </div>
  </div>
</div>

<script>
const API = '/api.php';
const COLORS = [
  '#e74c3c','#e67e22','#f1c40f','#2ecc71','#1abc9c',
  '#3498db','#9b59b6','#e91e63','#795548','#607d8b'
];
let reminders = [];
let calYear, calMonth;
let calView  = 'compact';
let selColor = null;

// ── Theme ──────────────────────────────────────────────────
document.addEventListener('schnueddels:themechange', (event) => {
  const theme = event?.detail?.theme;
  if (theme === 'light' || theme === 'dark') {
    const link = document.getElementById('themeStylesheet');
    if (link) {
      link.href = '/assets/css/themes/' + theme + '.css?v=<?= $themeVersion ?>';
    }
    api('POST', 'prefs_save', { theme });
  }
});

// ── Views ──────────────────────────────────────────────────
function showView(name) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('[data-view]').forEach(b => b.classList.toggle('active', b.dataset.view === name));
  document.getElementById('view-' + name).classList.add('active');
  document.getElementById('list-back-bar').style.display = 'none';
  if (name === 'calendar') renderCurrentView();
  if (name === 'list')     renderList();
  if (name === 'settings') loadPrefs();
}

function goBackToCalendar() {
  showView('calendar');
}

function setCalView(mode) {
  calView = mode;
  document.querySelectorAll('.seg-btn').forEach(b =>
    b.classList.toggle('active', b.dataset.mode === mode));
  renderCurrentView();
  api('POST', 'prefs_save', { cal_view: mode });
}

function renderCurrentView() {
  const grid     = document.getElementById('cal-grid');
  const calList  = document.getElementById('cal-list');
  const upcoming = document.getElementById('cal-upcoming');
  if (calView === 'list') {
    grid.style.display     = 'none';
    calList.style.display  = 'block';
    upcoming.style.display = 'none';
    renderListMode();
  } else {
    grid.style.display     = '';
    calList.style.display  = 'none';
    upcoming.style.display = '';
    grid.classList.toggle('mode-full', calView === 'full');
    renderCalendar();
    renderUpcoming();
  }
}

// ── API helper ─────────────────────────────────────────────
async function api(method, action, body) {
  const opts = { method, credentials: 'include', headers: { Accept: 'application/json' } };
  if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  const r = await fetch(API + '?action=' + action, opts);
  return r.json();
}

function calEffectiveColor(r) {
  return r.color || 'var(--ink)';
}

function contrastColor(hex) {
  if (!hex) return 'var(--bg)';
  if (hex.startsWith('var(')) return 'var(--ink)';
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.5 ? '#000' : '#fff';
}

// ── Calendar ───────────────────────────────────────────────
async function initCalendar() {
  const now  = new Date();
  calYear    = now.getFullYear();
  calMonth   = now.getMonth() + 1;

  try {
    const prefs = await api('GET', 'prefs_get');
    if (prefs && ['compact','full','list'].includes(prefs.cal_view)) {
      calView = prefs.cal_view;
      document.querySelectorAll('.seg-btn').forEach(b =>
        b.classList.toggle('active', b.dataset.mode === calView));
    }
  } catch (e) { /* Fallback auf compact */ }

  await loadMonth();
}

async function loadMonth() {
  const m    = String(calMonth).padStart(2, '0');
  const data = await api('GET', 'list&month=' + calYear + '-' + m);
  reminders  = Array.isArray(data) ? data : [];
  renderCurrentView();
}

function renderCalendar() {
  const m      = String(calMonth).padStart(2, '0');
  const days   = ['Mo','Di','Mi','Do','Fr','Sa','So'];
  const title  = new Date(calYear, calMonth - 1, 1).toLocaleString('de-DE', { month: 'long', year: 'numeric' });
  document.getElementById('cal-title').textContent = title;

  const grid   = document.getElementById('cal-grid');
  grid.innerHTML = days.map(d => `<div class="cal-day-hdr">${d}</div>`).join('');

  const first  = new Date(calYear, calMonth - 1, 1);
  let startDay = (first.getDay() + 6) % 7; // Mo=0
  const total  = new Date(calYear, calMonth, 0).getDate();
  const prevTotal = new Date(calYear, calMonth - 1, 0).getDate();

  const todayStr = new Date().toISOString().slice(0, 10);
  const byDay    = {};
  reminders.forEach(r => {
    const d = r.remind_at.slice(0, 10);
    if (!byDay[d]) byDay[d] = [];
    byDay[d].push(r);
  });

  for (let i = startDay - 1; i >= 0; i--) {
    grid.insertAdjacentHTML('beforeend',
      `<div class="cal-cell other-month"><div class="cal-day-num">${prevTotal - i}</div></div>`);
  }
  for (let d = 1; d <= total; d++) {
    const dateStr  = calYear + '-' + m + '-' + String(d).padStart(2,'0');
    const dayRems  = byDay[dateStr] || [];
    const today    = dateStr === todayStr ? ' today' : '';

    let inner = `<div class="cal-day-num">${d}</div>`;

    if (calView === 'compact' && dayRems.length > 0) {
      const seen = new Set();
      const dots = dayRems.map(r => {
        const c = calEffectiveColor(r);
        if (seen.has(c)) return '';
        seen.add(c);
        return `<span class="cal-dot" style="background:${c}"></span>`;
      }).join('');
      inner += `<div class="cal-dots">${dots}</div>`;
    }

    if (calView === 'full' && dayRems.length > 0) {
      const chips = dayRems.slice(0, 2).map(r => {
        const c   = calEffectiveColor(r);
        const fg  = contrastColor(r.color);
        const rmQ = JSON.stringify(r).replace(/"/g, '&quot;');
        return `<div class="cal-chip" style="background:${c};color:${fg}"
                     onclick="event.stopPropagation();openDialog(JSON.parse(this.dataset.r))"
                     data-r="${rmQ}">${esc(r.title)}</div>`;
      }).join('');
      const more = dayRems.length > 2
        ? `<div class="cal-chip-more">+${dayRems.length - 2}</div>` : '';
      inner += `<div class="cal-chips">${chips}${more}</div>`;
    }

    grid.insertAdjacentHTML('beforeend',
      `<div class="cal-cell${today}" onclick="openDay('${dateStr}')">${inner}</div>`);
  }
  const filled = startDay + total;
  const rem    = filled % 7 === 0 ? 0 : 7 - (filled % 7);
  for (let i = 1; i <= rem; i++) {
    grid.insertAdjacentHTML('beforeend',
      `<div class="cal-cell other-month"><div class="cal-day-num">${i}</div></div>`);
  }
}

function openDay(dateStr) {
  const dayReminders = reminders.filter(r => r.remind_at.startsWith(dateStr));
  if (dayReminders.length === 0) {
    document.getElementById('rm-datetime').value = dateStr + 'T09:00';
    openDialog(null);
    return;
  }
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.getElementById('view-list').classList.add('active');
  document.getElementById('list-back-bar').style.display = '';
  renderList(dayReminders);
}

function renderUpcoming() {
  const now      = new Date();
  const upcoming = reminders
    .filter(r => r.status !== 'done' && new Date(r.remind_at.replace(' ', 'T')) >= now)
    .sort((a, b) => a.remind_at.localeCompare(b.remind_at))
    .slice(0, 3);

  const container = document.getElementById('cal-upcoming');
  if (!upcoming.length) { container.innerHTML = ''; return; }

  container.innerHTML = '<div class="cal-upcoming">'
    + '<div class="cal-upcoming-title">Nächste Termine</div>'
    + upcoming.map(r => {
        const dt  = new Date(r.remind_at.replace(' ', 'T')).toLocaleString('de-DE',
          { weekday:'short', day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
        const rmQ = JSON.stringify(r).replace(/"/g, '&quot;');
        return `<div class="cal-upcoming-row" onclick="openDialog(JSON.parse(this.dataset.r))" data-r="${rmQ}">
          <span class="cal-upcoming-dt">${dt}</span>
          <span class="cal-upcoming-ttl">${esc(r.title)}</span>
        </div>`;
      }).join('')
    + '</div>';
}

async function calPrev() { calMonth--; if (calMonth < 1)  { calMonth = 12; calYear--; } await loadMonth(); }
async function calNext() { calMonth++; if (calMonth > 12) { calMonth = 1;  calYear++; } await loadMonth(); }

function renderListMode() {
  const calList  = document.getElementById('cal-list');
  const m        = String(calMonth).padStart(2, '0');
  const total    = new Date(calYear, calMonth, 0).getDate();
  const todayStr = new Date().toISOString().slice(0, 10);

  const byDay = {};
  reminders.forEach(r => {
    const d = r.remind_at.slice(0, 10);
    if (!byDay[d]) byDay[d] = [];
    byDay[d].push(r);
  });

  let html = '';
  for (let d = 1; d <= total; d++) {
    const dateStr  = calYear + '-' + m + '-' + String(d).padStart(2,'0');
    const date     = new Date(dateStr + 'T00:00:00');
    const dayLabel = date.toLocaleDateString('de-DE', { weekday:'short', day:'2-digit', month:'long' });
    const isToday  = dateStr === todayStr ? ' today' : '';
    const dayRems  = byDay[dateStr] || [];

    html += `<div class="list-day">`;
    html += `<div class="list-day-hdr${isToday}" onclick="listDayClick('${dateStr}')">${dayLabel}</div>`;

    if (dayRems.length === 0) {
      html += `<div class="list-day-empty" onclick="listDayClick('${dateStr}')">—</div>`;
    } else {
      dayRems.forEach(r => {
        const c   = calEffectiveColor(r);
        const time = r.remind_at.slice(11, 16);
        const rmQ = JSON.stringify(r).replace(/"/g, '&quot;');
        html += `<div class="list-rem-row" onclick="openDialog(JSON.parse(this.dataset.r))" data-r="${rmQ}">
          <span class="list-rem-dot" style="background:${c}"></span>
          <span class="list-rem-time">${time}</span>
          <span class="list-rem-title">${esc(r.title)}</span>
        </div>`;
      });
    }
    html += `</div>`;
  }
  calList.innerHTML = html;
}

function listDayClick(dateStr) {
  document.getElementById('rm-datetime').value = dateStr + 'T09:00';
  selColor = null;
  openDialog(null);
}

// ── List ───────────────────────────────────────────────────
async function renderList(data) {
  if (!data) {
    data = await api('GET', 'list');
    if (Array.isArray(data)) reminders = data;
    else data = reminders;
  }
  const container = document.getElementById('reminder-list');
  if (!data.length) {
    container.innerHTML = '<p style="color:var(--muted);text-align:center;padding:40px">Keine Reminders</p>';
    return;
  }

  container.innerHTML = data.map(r => {
    const dt  = new Date(r.remind_at.replace(' ', 'T')).toLocaleString('de-DE',
      { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    const rec = r.recurrence !== 'none' ? ' · ' + r.recurrence : '';
    const rmJson = JSON.stringify(r).replace(/"/g, '&quot;');
    return `<div class="reminder-card ${r.status}">
      <div class="reminder-info">
        <div class="reminder-title">${esc(r.title)}</div>
        <div class="reminder-meta">${dt}${rec} · ${r.status}</div>
        ${r.description ? `<div style="font-size:.85rem;margin-top:6px;color:var(--muted)">${esc(r.description)}</div>` : ''}
      </div>
      <div class="reminder-actions">
        <button class="btn-icon" onclick="openDialog(JSON.parse(this.dataset.r))" data-r="${rmJson}">✏️</button>
        ${r.status !== 'done' ? `<button class="btn-icon" onclick="markDone(${r.id})">✓</button>` : ''}
      </div>
    </div>`;
  }).join('');
}

async function markDone(id) {
  await api('POST', 'done', { id });
  renderList();
}

// ── Farbwähler ─────────────────────────────────────────────
function renderColorSwatches() {
  const container = document.getElementById('color-swatches');
  let html = `<button type="button"
    class="color-swatch color-swatch-none${selColor === null ? ' active' : ''}"
    onclick="pickColor(null)" title="Keine Farbe">✕</button>`;
  COLORS.forEach(c => {
    html += `<button type="button"
      class="color-swatch${selColor === c ? ' active' : ''}"
      style="background:${c}"
      onclick="pickColor('${c}')"
      title="${c}"></button>`;
  });
  container.innerHTML = html;
}

function pickColor(c) {
  selColor = c;
  renderColorSwatches();
}

// ── Dialog ─────────────────────────────────────────────────
function openDialog(reminder) {
  document.getElementById('modal').classList.remove('hidden');
  document.getElementById('rm-id').value              = reminder?.id ?? '';
  document.getElementById('rm-title').value           = reminder?.title ?? '';
  document.getElementById('rm-desc').value            = reminder?.description ?? '';
  document.getElementById('rm-recurrence').value      = reminder?.recurrence ?? 'none';
  document.getElementById('rm-recurrence-end').value  = reminder?.recurrence_end ?? '';
  document.getElementById('rm-email-notify').checked  = !!reminder?.email_notify;
  document.getElementById('rm-email-lead').value      = reminder?.email_lead_time ?? '';
  document.getElementById('modal-title').textContent  = reminder ? 'Reminder bearbeiten' : 'Neuer Reminder';
  document.getElementById('delete-btn').style.display = reminder ? '' : 'none';

  if (reminder?.remind_at) {
    document.getElementById('rm-datetime').value = reminder.remind_at.replace(' ', 'T').slice(0, 16);
  } else if (!document.getElementById('rm-datetime').value) {
    const n = new Date(); n.setMinutes(0, 0, 0); n.setHours(n.getHours() + 1);
    const pad = v => String(v).padStart(2, '0');
    document.getElementById('rm-datetime').value =
      `${n.getFullYear()}-${pad(n.getMonth()+1)}-${pad(n.getDate())}T${pad(n.getHours())}:${pad(n.getMinutes())}`;
  }
  toggleRecurrenceEnd();
  toggleEmailLead();
  selColor = reminder ? (reminder.color || null) : null;
  renderColorSwatches();
}

function closeDialog() {
  document.getElementById('modal').classList.add('hidden');
  document.getElementById('rm-datetime').value = '';
}

function toggleRecurrenceEnd() {
  const v = document.getElementById('rm-recurrence').value;
  document.getElementById('recurrence-end-wrap').style.display = v !== 'none' ? '' : 'none';
}

function toggleEmailLead() {
  const v = document.getElementById('rm-email-notify').checked;
  document.getElementById('email-lead-wrap').style.display = v ? '' : 'none';
}

async function saveReminder() {
  const id    = document.getElementById('rm-id').value;
  const title = document.getElementById('rm-title').value.trim();
  const dt    = document.getElementById('rm-datetime').value;
  if (!title || !dt) { alert('Titel und Datum sind Pflichtfelder.'); return; }

  const leadVal = document.getElementById('rm-email-lead').value;
  const data = {
    title,
    description:     document.getElementById('rm-desc').value.trim() || null,
    color:           selColor,
    remind_at:       dt.replace('T', ' '),
    recurrence:      document.getElementById('rm-recurrence').value,
    recurrence_end:  document.getElementById('rm-recurrence-end').value || null,
    email_notify:    document.getElementById('rm-email-notify').checked ? 1 : 0,
    email_lead_time: leadVal ? parseInt(leadVal) : null,
  };

  if (id) {
    data.id = parseInt(id);
    await api('POST', 'update', data);
  } else {
    await api('POST', 'create', data);
  }
  closeDialog();
  await loadMonth();
  if (document.getElementById('view-list').classList.contains('active')) renderList();
}

async function deleteReminder() {
  if (!confirm('Reminder wirklich löschen?')) return;
  const id = parseInt(document.getElementById('rm-id').value);
  await api('POST', 'delete', { id });
  closeDialog();
  await loadMonth();
  if (document.getElementById('view-list').classList.contains('active')) renderList();
}

// ── Settings ───────────────────────────────────────────────
async function loadPrefs() {
  const p = await api('GET', 'prefs_get');
  if (!p.error) {
    document.getElementById('pref-lead').value  = p.default_email_lead ?? 1440;
    document.getElementById('pref-email').value = p.email_override ?? '';
  }
  updatePushStatus();
}

async function savePrefs() {
  const lead  = parseInt(document.getElementById('pref-lead').value);
  const email = document.getElementById('pref-email').value.trim() || null;
  const r = await api('POST', 'prefs_save', { default_email_lead: lead, email_override: email });
  alert(r.ok ? 'Gespeichert.' : 'Fehler: ' + r.error);
}

async function updatePushStatus() {
  if (!('Notification' in window)) {
    document.getElementById('push-status').textContent = 'Browser unterstützt keine Push-Benachrichtigungen.';
    document.getElementById('push-btn').style.display = 'none';
    return;
  }
  const perm = Notification.permission;
  document.getElementById('push-status').textContent =
    perm === 'granted' ? '✅ Push aktiviert' :
    perm === 'denied'  ? '❌ Push verweigert (in Browser-Einstellungen erlauben)' :
                         '⏳ Noch nicht aktiviert';
  document.getElementById('push-btn').textContent = perm === 'granted' ? 'Deaktivieren' : 'Aktivieren';
}

async function togglePush() {
  if (Notification.permission === 'granted') {
    if ('serviceWorker' in navigator) {
      const reg = await navigator.serviceWorker.getRegistration('https://reminder.schnueddels.de/');
      if (reg) {
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
          await api('POST', 'unsubscribe_push', { endpoint: sub.endpoint });
          await sub.unsubscribe();
        }
      }
    }
  } else {
    if (typeof window.__rmInitPush === 'function') await window.__rmInitPush();
  }
  updatePushStatus();
}

// ── Import ─────────────────────────────────────────────────
let importData = null;

document.getElementById('file-input').addEventListener('change', e => handleFile(e.target.files[0]));
const dz = document.getElementById('dropzone');
dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('drag-over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
dz.addEventListener('drop',      e => { e.preventDefault(); dz.classList.remove('drag-over'); handleFile(e.dataTransfer.files[0]); });

async function handleFile(file) {
  if (!file) return;
  const form = new FormData();
  form.append('file', file);
  form.append('action', 'preview');
  const r    = await fetch('/import.php', { method: 'POST', credentials: 'include', body: form });
  const data = await r.json();
  if (data.error) { alert('Fehler: ' + data.error); return; }
  if (data.total === 0) { alert('Keine gültigen Einträge gefunden. Bitte Format prüfen (Spalten: title, datetime, recurrence, description).'); return; }
  importData = data;
  renderImportPreview(data);
}

function renderImportPreview(data) {
  const tbody = document.getElementById('import-tbody');
  tbody.innerHTML = data.items.slice(0, 50).map(i =>
    `<tr><td>${esc(i.title)}</td><td>${esc(i.remind_at)}</td><td>${esc(i.recurrence)}</td></tr>`
  ).join('');
  document.getElementById('import-summary').textContent =
    `${data.total} Einträge erkannt (${data.skipped_preview} übersprungen / Duplikate)`;
  document.getElementById('import-preview').style.display = '';
}

async function confirmImport() {
  if (!importData) return;
  const r = await fetch('/import.php', {
    method: 'POST', credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'confirm', items: importData.items, format: importData.format ?? 'csv' }),
  });
  const data = await r.json();
  document.getElementById('import-preview').style.display = 'none';
  const res = document.getElementById('import-result');
  res.style.display = '';
  res.innerHTML = data.error
    ? `Fehler: ${esc(data.error)}`
    : `✅ ${data.imported} Reminder importiert, ${data.skipped} übersprungen.`;
  importData = null;
  document.getElementById('file-input').value = '';
  await loadMonth();
}

function resetImport() {
  importData = null;
  document.getElementById('import-preview').style.display = 'none';
  document.getElementById('import-result').style.display  = 'none';
  document.getElementById('file-input').value = '';
}

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ───────────────────────────────────────────────────
initCalendar();
</script>
</body>
</html>
