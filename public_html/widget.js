(function () {
  'use strict';

  const API    = 'https://reminder.schnueddels.de/api.php';
  const SW_URL = 'https://reminder.schnueddels.de/sw.js';

  function hasCookie() {
    return document.cookie.split(';').some(c => c.trim().startsWith('AUTHSESS='));
  }

  if (!hasCookie()) return;

  function urlBase64ToUint8Array(b64) {
    const pad = '='.repeat((4 - b64.length % 4) % 4);
    const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, c => c.charCodeAt(0));
  }

  async function getVapidKey() {
    const cached = localStorage.getItem('rm_vapid');
    if (cached) return cached;
    try {
      const r = await fetch(API + '?action=config', { credentials: 'include' });
      if (!r.ok) return null;
      const { vapidPublicKey } = await r.json();
      if (vapidPublicKey) localStorage.setItem('rm_vapid', vapidPublicKey);
      return vapidPublicKey || null;
    } catch { return null; }
  }

  async function initPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
    try {
      const vapid = await getVapidKey();
      if (!vapid) return false;

      const reg      = await navigator.serviceWorker.register(SW_URL);
      await navigator.serviceWorker.ready;
      const existing = await reg.pushManager.getSubscription();

      if (existing) {
        await saveSubscription(existing);
        return true;
      }
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') return false;

      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapid),
      });
      await saveSubscription(sub);
      return true;
    } catch { return false; }
  }

  async function saveSubscription(sub) {
    await fetch(API + '?action=subscribe_push', {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(sub.toJSON()),
    }).catch(() => {});
  }

  // Expose for Settings page deactivation
  window.__rmInitPush = initPush;

  const CHECK_INTERVAL_MS = 5 * 60 * 1000;
  let pendingQueue = [];
  let lastCheckAt = 0;
  let checkTimer = null;

  async function checkDue(force = false) {
    const key = 'rm_chk_' + location.hostname;
    const now = Date.now();
    const last = Math.max(
      lastCheckAt,
      parseInt(localStorage.getItem(key) || '0', 10)
    );
    if (!force && now - last < CHECK_INTERVAL_MS) return;
    lastCheckAt = now;
    localStorage.setItem(key, String(now));
    try {
      const r = await fetch(API + '?action=due', {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
      if (!r.ok) return;
      const list = await r.json();
      if (!Array.isArray(list) || list.length === 0) return;
      pendingQueue = list.slice(1);
      // Kurz warten damit andere Seiten-Dialoge (z.B. Tutorial) zuerst erscheinen
      setTimeout(() => showPopup(list[0]), 3000);
    } catch {}
  }

  function showPopup(reminder) {
    document.getElementById('rm-popup')?.remove();

    const dt = new Date(reminder.remind_at.replace(' ', 'T')).toLocaleString('de-DE', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });

    if (!document.getElementById('rm-style')) {
      const s = document.createElement('style');
      s.id = 'rm-style';
      s.textContent = [
        '#rm-popup{position:fixed;bottom:20px;right:20px;z-index:2147483647;max-width:320px;font-family:system-ui,sans-serif}',
        '#rm-inner{background:#1e1e2e;color:#cdd6f4;border:1px solid #45475a;border-radius:12px;padding:16px;box-shadow:0 4px 24px rgba(0,0,0,.5)}',
        '#rm-head{display:flex;gap:8px;align-items:center;font-weight:700;font-size:1rem;margin-bottom:6px}',
        '#rm-time{font-size:.82rem;color:#a6adc8;margin-bottom:8px}',
        '#rm-desc{font-size:.85rem;color:#bac2de;margin-bottom:10px}',
        '#rm-btns{display:flex;gap:6px;flex-wrap:wrap}',
        '#rm-btns button{border:1px solid #45475a;background:transparent;color:#cdd6f4;border-radius:8px;padding:5px 10px;cursor:pointer;font-size:.82rem}',
        '#rm-btns button:hover{background:#313244}',
        '#rm-btns .rm-ok{color:#a6e3a1;border-color:#a6e3a1}',
        '#rm-btns .rm-x{margin-left:auto}',
      ].join('');
      document.head.appendChild(s);
    }

    const el = document.createElement('div');
    el.id = 'rm-popup';
    el.innerHTML = `<div id="rm-inner">
      <div id="rm-head"><span>🔔</span><span>${esc(reminder.title)}</span></div>
      <div id="rm-time">${dt}</div>
      ${reminder.description ? `<div id="rm-desc">${esc(reminder.description)}</div>` : ''}
      <div id="rm-btns">
        <button data-a="s10">+10 min</button>
        <button data-a="s60">+1h</button>
        <button data-a="done" class="rm-ok">✓ Erledigt</button>
        <button data-a="dismiss" class="rm-x">✕</button>
      </div>
    </div>`;
    document.body.appendChild(el);

    el.addEventListener('click', async e => {
      const btn = e.target.closest('[data-a]');
      if (!btn) return;
      const a = btn.dataset.a;
      if (a === 'dismiss') { el.remove(); showNext(); return; }
      if (a === 'done')    { await post('done',   { id: reminder.id }); el.remove(); showNext(); return; }
      if (a === 's10')     { await post('snooze', { id: reminder.id, minutes: 10 }); el.remove(); showNext(); return; }
      if (a === 's60')     { await post('snooze', { id: reminder.id, minutes: 60 }); el.remove(); showNext(); return; }
    });
  }

  function showNext() {
    if (pendingQueue.length) showPopup(pendingQueue.shift());
  }

  async function post(action, data) {
    await fetch(API + '?action=' + action, {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    }).catch(() => {});
  }

  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  window.addEventListener('load', async () => {
    const pushOk = await initPush();
    await checkDue();
    checkTimer = window.setInterval(() => {
      if (document.visibilityState === 'visible') {
        void checkDue();
      }
    }, CHECK_INTERVAL_MS);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        void checkDue(true);
      }
    });
    window.addEventListener('focus', () => {
      void checkDue(true);
    });
    void pushOk;
  });
})();
