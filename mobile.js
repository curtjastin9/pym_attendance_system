/* ============================================================
   PYM — Mobile & accessibility helpers (load LAST)
   • Non-blocking toasts replace alert()
   • Tables get data-labels so they can render as cards on phones
   • Bottom navigation, drawer a11y, password show/hide
   ============================================================ */
(function () {
  'use strict';

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
    // New page → start at the top
    document.querySelectorAll('.sidebar .nav-item').forEach(n => n.addEventListener('click', () => window.scrollTo({ top: 0 })));

    const nav = document.querySelector('.bottom-nav');
    if (!nav) return;
    const items = Array.from(nav.querySelectorAll('.bn-item[data-page]'));
    const more = nav.querySelector('.bn-more');

    // Click the real sidebar item so every page's own handlers (e.g. Records) still run
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

  /* ---------- Keep the focused field visible above the keyboard ---------- */
  document.addEventListener('focusin', e => {
    if (window.innerWidth > 768) return;
    if (!/^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName)) return;
    setTimeout(() => { try { e.target.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (_) {} }, 320);
  });

  function boot() {
    enhanceTables();
    initNav();
    initPasswords();
    // Make sure this stylesheet is last in the cascade (wins over injected <style> blocks)
    const link = document.querySelector('link[href^="mobile.css"]');
    if (link) document.head.appendChild(link);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
