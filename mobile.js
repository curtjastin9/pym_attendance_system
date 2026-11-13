/* ============================================================
   PYM — Speed layer + mobile, tablet & accessibility helpers (load LAST)
   • Speed: removes duplicate requests, short-lived cache for reads,
     retry on network blips, double-submit protection, top progress bar,
     lazy-loaded Excel library
   • Loads responsive.css (tablet / laptop / touch layer)
   • Non-blocking toasts replace alert()
   • Tables get data-labels so they can render as cards on phones
   • Bottom navigation, drawer a11y, password show/hide
   • Keyboard-aware bottom bar, skip link, drawer reset on resize
   ============================================================ */
(function () {
  'use strict';

  /* ============================================================
     SPEED LAYER — wraps fetch() for every api*.php?action=... call
     ============================================================ */
  (function speed() {
    if (window.__pymSpeed || typeof window.fetch !== 'function') return;
    window.__pymSpeed = true;

    const nativeFetch = window.fetch.bind(window);
    const API_RE = /(?:^|\/)(api[a-z_]*\.php)\?action=([a-z_]+)/i;
    const READ_RE = /^(get_[a-z_]+|get|check_session|list|options|users|my_account|admin_accounts|locked_ids|search_members|find_event)$/;
    const HEAVY_RE = /^(get_records|get_monthly_calendar|get_alerts|get_dashboard_stats|get_report)$/;
    const store = new Map();   // key -> entry
    let gen = 0, pending = 0, barTimer = null, bar = null;

    const sleep = ms => new Promise(r => setTimeout(r, ms));
    const mk = o => new Response(o.text, { status: o.status, headers: { 'Content-Type': 'application/json' } });

    /* thin progress bar (only shows if something takes longer than 0.35s) */
    const css = '.pym-bar{position:fixed;left:0;top:0;height:3px;width:100%;z-index:10001;pointer-events:none;opacity:0;overflow:hidden;transition:opacity .2s}' +
      '.pym-bar.on{opacity:1}.pym-bar i{display:block;height:100%;width:40%;background:linear-gradient(90deg,transparent,#F5B82E,#3B82F6,transparent);transform:translateX(-100%);animation:pymbar 1.1s linear infinite;will-change:transform}' +
      '@keyframes pymbar{from{transform:translateX(-100%)}to{transform:translateX(350%)}}';
    const sty = document.createElement('style'); sty.textContent = css; document.head.appendChild(sty);
    function busy(d) {
      pending = Math.max(0, pending + d);
      if (!bar && document.body) { bar = document.createElement('div'); bar.className = 'pym-bar'; bar.innerHTML = '<i></i>'; bar.setAttribute('aria-hidden', 'true'); document.body.appendChild(bar); }
      clearTimeout(barTimer);
      if (pending > 0) barTimer = setTimeout(() => bar && bar.classList.add('on'), 350);
      else if (bar) bar.classList.remove('on');
    }

    async function net(url, init, retry) {
      for (let i = 0; ; i++) {
        try {
          const r = await nativeFetch(url, init);
          const text = await r.text();
          const bad = r.status >= 500 || text.trim().charAt(0) !== '{';
          if (bad && retry && i === 0) { await sleep(700); continue; }   // one quiet retry for reads (cold start / network blip)
          return { text, status: r.status };
        } catch (e) {
          if (retry && i === 0) { await sleep(700); continue; }
          throw e;
        }
      }
    }

    function invalidate(action) {
      const keepSession = action !== 'login' && action !== 'logout';
      for (const [k, v] of store) {
        if (v.write) continue;
        if (v.action === 'check_session' && keepSession) continue;
        store.delete(k);
      }
    }

    window.fetch = function (url, init) {
      if (typeof url !== 'string' || !init || String(init.method || '').toUpperCase() !== 'POST' || typeof init.body !== 'string') return nativeFetch(url, init);
      const m = url.match(API_RE);
      if (!m) return nativeFetch(url, init);

      const action = m[2].toLowerCase();
      const isRead = READ_RE.test(action);
      const key = url + '|' + init.body;

      // Same request already running or freshly answered → reuse it (also stops double-tap duplicate saves)
      const hit = store.get(key);
      if (hit && (hit.pending || Date.now() - hit.t < hit.ttl)) return hit.p.then(mk);

      const entry = {
        action, write: !isRead, pending: true, t: 0, gen,
        ttl: action === 'check_session' ? Infinity : (HEAVY_RE.test(action) ? 10000 : 4000)
      };

      let src;
      const pre = isRead && window.__pre && window.__pre[key];
      if (pre) {
        delete window.__pre[key];
        src = (Date.now() - pre.t0 < 8000) ? pre.p.then(o => o || net(url, init, true)) : net(url, init, true);
      } else {
        busy(1);
        src = net(url, init, isRead);
        src.then(() => busy(-1), () => busy(-1));
      }

      entry.p = src.then(o => {
        entry.pending = false; entry.t = Date.now();
        const ok = /"success"\s*:\s*true/.test(o.text);
        if (entry.write) { if (store.get(key) === entry) store.delete(key); gen++; invalidate(action); }
        else if (!ok || entry.gen !== gen) { if (store.get(key) === entry) store.delete(key); }
        return o;
      }, e => { if (store.get(key) === entry) store.delete(key); throw e; });

      store.set(key, entry);
      if (!isRead) { gen++; invalidate(action); }
      return entry.p.then(mk);
    };

    /* Excel library (~900KB) is only downloaded when it is really needed */
    let xl = null;
    window.pymLoadXLSX = function () {
      if (typeof XLSX !== 'undefined') return Promise.resolve();
      if (xl) return xl;
      xl = new Promise((ok, bad) => {
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        s.onload = ok;
        s.onerror = () => { xl = null; bad(new Error('xlsx')); };
        document.head.appendChild(s);
      });
      return xl;
    };
    if (typeof recExport === 'function') {
      const _re = recExport;
      recExport = async function () {
        try { await window.pymLoadXLSX(); }
        catch (e) { alert('The Excel library could not be loaded. Check your internet connection and try again.'); return; }
        return _re.apply(this, arguments);
      };
    }
  })();

  /* ---------- Load the responsive layer ---------- */
  if (!document.querySelector('link[href^="responsive.css"]')) {
    const rl = document.createElement('link');
    rl.rel = 'stylesheet';
    rl.href = 'responsive.css?v=1';
    document.head.appendChild(rl);
  }

  /* ---------- Toasts ---------- */
  const css = `
  .toast-host{position:fixed;left:0;right:0;top:calc(10px + env(safe-area-inset-top));z-index:9999;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none;padding:0 12px}
  .toast{pointer-events:auto;display:flex;align-items:flex-start;gap:11px;max-width:460px;width:100%;padding:14px 16px;border-radius:14px;background:#0F172A;color:#fff;font:600 13.5px/1.45 Inter,system-ui,-apple-system,sans-serif;box-shadow:0 14px 40px rgba(0,0,0,.30);border-left:4px solid #10B981;transform:translateY(-18px);opacity:0;transition:transform .28s cubic-bezier(.2,.8,.2,1),opacity .28s}
  .toast.err{border-left-color:#F87171}
  .toast.show{transform:none;opacity:1}
  .toast i{margin-top:2px;font-size:15px}.toast.ok i{color:#34D399}.toast.err i{color:#FCA5A5}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  const host = document.createElement('div');
  host.className = 'toast-host';
  host.setAttribute('role', 'status');
  host.setAttribute('aria-live', 'polite');
  document.body.appendChild(host);

  const ERR = /(fail|error|invalid|cannot|can't|expired|permission|please|select a|required|too many|network|do not have|unable|no access|not scheduled|offline)/i;
  function toast(msg) {
    msg = String(msg == null ? '' : msg).trim();
    if (!msg) return;
    const bad = ERR.test(msg);
    const t = document.createElement('div');
    t.className = 'toast ' + (bad ? 'err' : 'ok');
    t.innerHTML = '<i class="fas ' + (bad ? 'fa-circle-exclamation' : 'fa-circle-check') + '" aria-hidden="true"></i><span></span>';
    t.querySelector('span').textContent = msg;
    host.appendChild(t);
    while (host.children.length > 3) host.firstChild.remove();
    requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
    const close = () => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); };
    t.addEventListener('click', close);
    setTimeout(close, Math.min(7000, 2800 + msg.length * 40));
  }
  window.alert = toast;
  window.addEventListener('offline', () => toast('You are offline. Changes cannot be saved until you reconnect.'));
  window.addEventListener('online', () => toast('Back online.'));

  /* ---------- Tables → data-labels (phone card layout) ---------- */
  function labelTable(table) {
    const heads = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    table.querySelectorAll('tbody tr').forEach(tr => {
      Array.from(tr.children).forEach((td, i) => {
        if (td.tagName === 'TD' && !td.hasAttribute('data-label') && !td.hasAttribute('colspan') && heads[i]) {
          td.setAttribute('data-label', heads[i]);
        }
      });
    });
  }
  function enhanceTables() {
    document.querySelectorAll('table.data-table').forEach(t => {
      if (t.dataset.mob) return;
      t.dataset.mob = '1';
      t.classList.add('stack');
      const tb = t.tBodies[0];
      if (tb && tb.id === 'recTableBody') t.classList.add('stack-rec');
      if (tb && (tb.id === 'attendanceTableBody' || tb.id === 'evAttTableBody')) t.classList.add('att-cards');
      labelTable(t);
      new MutationObserver(() => labelTable(t)).observe(t, { childList: true, subtree: true });
    });
  }

  /* ---------- Bottom navigation + drawer a11y ---------- */
  function initNav() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const hb = document.getElementById('hamburger');
    const overlay = document.getElementById('overlay');

    if (hb) {
      new MutationObserver(() => hb.setAttribute('aria-expanded', sidebar.classList.contains('open') ? 'true' : 'false'))
        .observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    }
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && sidebar.classList.contains('open') && overlay) overlay.click();
    });
    document.querySelectorAll('.sidebar .nav-item').forEach(n => n.addEventListener('click', () => window.scrollTo({ top: 0 })));

    // Warm up the Excel library as soon as the Records page is opened
    const rec = document.querySelector('.nav-item[data-page="records"]');
    if (rec) rec.addEventListener('click', () => { if (window.pymLoadXLSX) window.pymLoadXLSX().catch(() => {}); });

    // Rotating a tablet / resizing a window to laptop size: close the drawer
    let lastW = window.innerWidth;
    window.addEventListener('resize', () => {
      const w = window.innerWidth;
      if (w > 1023 && lastW <= 1023) {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
      }
      lastW = w;
    });

    // Skip link for keyboard users (laptop)
    const main = document.getElementById('mainContent');
    if (main && !document.querySelector('.skip-link')) {
      main.setAttribute('tabindex', '-1');
      const a = document.createElement('a');
      a.className = 'skip-link';
      a.href = '#mainContent';
      a.textContent = 'Skip to content';
      document.body.insertBefore(a, document.body.firstChild);
    }

    const nav = document.querySelector('.bottom-nav');
    if (!nav) return;
    const items = Array.from(nav.querySelectorAll('.bn-item[data-page]'));
    const more = nav.querySelector('.bn-more');

    items.forEach(b => b.addEventListener('click', () => {
      const real = document.querySelector('.sidebar .nav-item[data-page="' + b.dataset.page + '"]');
      if (real) real.click();
    }));
    if (more && hb) more.addEventListener('click', () => hb.click());

    const sync = () => {
      const a = document.querySelector('.sidebar .nav-item.active');
      const p = a ? a.dataset.page : '';
      let hit = false;
      items.forEach(b => {
        const on = b.dataset.page === p;
        b.classList.toggle('active', on);
        if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
        hit = hit || on;
      });
      if (more) more.classList.toggle('active', !hit && !!p);
    };
    const sn = document.querySelector('.sidebar-nav');
    if (sn) new MutationObserver(sync).observe(sn, { attributes: true, subtree: true, attributeFilter: ['class'] });
    sync();

    // Leaders don't get Records
    if (typeof apiCall === 'function') {
      apiCall('check_session').then(r => {
        if (r && r.success && r.data.role !== 'secretariat') nav.querySelectorAll('.secretariat-only').forEach(e => e.remove());
        nav.style.setProperty('--bn', nav.children.length);
      });
    }
  }

  /* ---------- Password show / hide ---------- */
  function initPasswords() {
    document.querySelectorAll('input[type="password"]').forEach(inp => {
      if (inp.parentElement.classList.contains('pw-wrap')) return;
      const w = document.createElement('div');
      w.className = 'pw-wrap';
      inp.parentNode.insertBefore(w, inp);
      w.appendChild(inp);
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'pw-toggle';
      b.setAttribute('aria-label', 'Show password');
      b.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i>';
      b.addEventListener('click', () => {
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        b.innerHTML = '<i class="fas ' + (show ? 'fa-eye-slash' : 'fa-eye') + '" aria-hidden="true"></i>';
      });
      w.appendChild(b);
    });
  }

  /* ---------- Keyboard-aware: keep the field visible, hide the bottom bar while typing ---------- */
  let kbTimer = null;
  const isTouch = () => window.matchMedia('(pointer:coarse)').matches || window.innerWidth <= 768;
  document.addEventListener('focusin', e => {
    const t = e.target;
    if (!/^(INPUT|SELECT|TEXTAREA)$/.test(t.tagName)) return;
    if (/^(checkbox|radio|button|submit)$/.test(t.type)) return;
    if (!isTouch()) return;
    clearTimeout(kbTimer);
    document.body.classList.add('kb');
    setTimeout(() => { try { t.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (_) {} }, 320);
  });
  document.addEventListener('focusout', () => {
    clearTimeout(kbTimer);
    kbTimer = setTimeout(() => document.body.classList.remove('kb'), 150);
  });

  function boot() {
    enhanceTables();
    initNav();
    initPasswords();
    ['mobile.css', 'responsive.css'].forEach(n => {
      const link = document.querySelector('link[href^="' + n + '"]');
      if (link) document.head.appendChild(link);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
