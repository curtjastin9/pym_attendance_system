/* PYM Admin Events + Events page enhancements. Load after app.js (loaded last in index.php). */
/* ---------- Events: year bar + status filter ---------- */
(function () {
  const st = document.createElement('style');
  st.textContent = `.ev-bar{display:flex;flex-direction:column;gap:10px;margin:0 0 14px}
  .ev-row{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
  .ev-lbl{font-size:10.5px;font-weight:800;letter-spacing:.8px;color:#64748B;text-transform:uppercase;min-width:52px}
  .ev-btn{border:1.5px solid #CBD5E1;background:#fff;border-radius:99px;padding:6px 16px;font-size:12.5px;font-weight:800;cursor:pointer;color:#475569}
  .ev-btn small{opacity:.7;margin-left:5px;font-weight:700}
  .ev-btn.on{background:#1D4ED8;border-color:#1D4ED8;color:#fff}
  .ev-btn.s-done.on{background:#059669;border-color:#059669}
  .ev-btn.s-now.on{background:#D97706;border-color:#D97706}
  .ev-btn.s-up.on{background:#7C3AED;border-color:#7C3AED}
  .ev-st{display:inline-block;font-size:10.5px;font-weight:800;border-radius:99px;padding:2px 8px;margin-top:3px}
  .ev-st-done{background:#D1FAE5;color:#047857}.ev-st-now{background:#FEF3C7;color:#B45309}.ev-st-up{background:#EDE9FE;color:#6D28D9}
  tr.ev-done td{background:#F0FDF4}tr.ev-done td:first-child{box-shadow:inset 5px 0 0 #10B981}
  tr.ev-now td{background:#FFFBEB}tr.ev-now td:first-child{box-shadow:inset 5px 0 0 #F59E0B}
  tr.ev-up td{background:#F5F3FF}tr.ev-up td:first-child{box-shadow:inset 5px 0 0 #7C3AED}`;
  document.head.appendChild(st);
  const STATE = {}, DAY = 864e5, YEARS = ['2026', '2027', '2028', '2029', '2030'];
  const LBL = { all: 'All', done: 'Done', now: 'Ongoing (today)', up: 'Upcoming' };
  function info(d) {
    const t = new Date(); t.setHours(0, 0, 0, 0);
    const n = Math.round((new Date(d + 'T00:00:00') - t) / DAY);
    if (n < 0) return { k: 'done', t: 'Done' };
    if (n === 0) return { k: 'now', t: 'Ongoing today' };
    return { k: 'up', t: n === 1 ? 'Tomorrow' : n < 60 ? 'In ' + n + ' days' : 'In ' + Math.round(n / 30) + ' months' };
  }
  function paint(id) {
    const tb = document.getElementById(id);
    if (!tb) return;
    const evs = (typeof ALL_EVENTS !== 'undefined' && Array.isArray(ALL_EVENTS)) ? ALL_EVENTS : [];
    const rows = Array.from(tb.querySelectorAll('tr:not(.ev-empty)'));
    const has = evs.length && rows.length === evs.length && !rows[0].querySelector('.empty-msg');
    const items = [];
    if (has) rows.forEach((r, i) => {
      const d = String(evs[i].event_date || '').substring(0, 10), s = info(d);
      r.dataset.y = d.substring(0, 4); r.dataset.k = s.k;
      r.classList.remove('ev-done', 'ev-now', 'ev-up'); r.classList.add('ev-' + s.k);
      const td = r.cells[1]; td.querySelectorAll('.ev-st').forEach(x => x.remove());
      td.insertAdjacentHTML('beforeend', '<br><span class="ev-st ev-st-' + s.k + '">' + s.t + '</span>');
      items.push({ y: d.substring(0, 4), k: s.k });
    });
    const ys = Array.from(new Set(YEARS.concat(items.map(x => x.y)))).sort();
    const S = STATE[id] || (STATE[id] = { y: String(new Date().getFullYear()), k: 'all' });
    let bar = tb._evBar;
    if (!bar) {
      bar = document.createElement('div'); bar.className = 'ev-bar'; tb._evBar = bar;
      const w = tb.closest('.table-wrap') || tb.closest('table');
      w.parentNode.insertBefore(bar, w);
      bar.addEventListener('click', e => {
        const b = e.target.closest('.ev-btn'); if (!b) return;
        if (b.dataset.y) S.y = b.dataset.y; if (b.dataset.k) S.k = b.dataset.k;
        render();
      });
    }
    function render() {
      const inY = items.filter(x => S.y === 'all' || x.y === S.y);
      const cnt = k => inY.filter(x => k === 'all' || x.k === k).length;
      let shown = 0;
      if (has) rows.forEach(r => { const ok = (S.y === 'all' || r.dataset.y === S.y) && (S.k === 'all' || r.dataset.k === S.k); r.style.display = ok ? '' : 'none'; if (ok) shown++; });
      tb.querySelectorAll('.ev-empty').forEach(x => x.remove());
      if (has && !shown) tb.insertAdjacentHTML('beforeend', '<tr class="ev-empty"><td colspan="6" class="empty-msg">No events for this filter.</td></tr>');
      bar.innerHTML =
        '<div class="ev-row"><span class="ev-lbl">Year</span>' + ['all'].concat(ys).map(y => '<button type="button" class="ev-btn' + (y === S.y ? ' on' : '') + '" data-y="' + y + '">' + (y === 'all' ? 'All' : y) +
          '<small>' + items.filter(x => y === 'all' || x.y === y).length + '</small></button>').join('') + '</div>' +
        '<div class="ev-row"><span class="ev-lbl">Status</span>' + ['all', 'done', 'now', 'up'].map(k => '<button type="button" class="ev-btn s-' + k + (k === S.k ? ' on' : '') + '" data-k="' + k + '">' + LBL[k] + '<small>' + cnt(k) + '</small></button>').join('') + '</div>';
    }
    render();
  }
  const _le = loadEvents;
  loadEvents = async function (a) { await _le(a); try { paint(a ? 'adminEventsTableBody' : 'eventsTableBody'); } catch (e) {} };
})();

/* ---------- Events: live refresh (no manual reload) ---------- */
(function () {
  const WRITES = /^(save_event|delete_event|save_category_event|delete_category|save_category|restore_|bulk_)/;
  const on = id => { const p = document.getElementById(id); return p && p.classList.contains('active'); };
  const active = () => on('page-events') || on('page-adminevents');
  const refresh = () => { try {
    if (on('page-events') && typeof loadEvents === 'function') loadEvents(false);
    if (on('page-adminevents')) { loadEvents(true); if (typeof loadAdminCategories === 'function') loadAdminCategories(); }
  } catch (e) {} };
  const _api = apiCall;
  apiCall = async function (action, payload) {
    const r = await _api(action, payload);
    try { if (r && r.success && WRITES.test(String(action))) setTimeout(refresh, 150); } catch (e) {}
    return r;
  };
  setInterval(() => { if (active() && !document.hidden) refresh(); }, 15000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden && active()) refresh(); });
})();

/* ---------- Admin Events page: tabs, title, loading ---------- */
(function () {
  const page = document.getElementById('page-adminevents'), nav = document.querySelector('.nav-item[data-page="adminevents"]');
  if (!page || !nav) return;
  const st = document.createElement('style');
  st.textContent = `.ae-tabs{display:flex;gap:6px;flex-wrap:wrap;background:#fff;border:1px solid #E2E8F0;border-radius:14px;padding:6px;margin-bottom:18px;width:fit-content;max-width:100%}
  .ae-tab{border:0;background:transparent;border-radius:10px;padding:10px 16px;font-size:13px;font-weight:700;color:#475569;cursor:pointer;display:flex;align-items:center;gap:8px}
  .ae-tab.active{background:#0B1220;color:#fff}
  .nav-item.ae-nav{padding-left:34px;font-size:13px}`;
  document.head.appendChild(st);
  const secs = { 'admin-events': document.getElementById('admin-events'), 'admin-categories': document.getElementById('admin-categories') };
  let cur = 'admin-events';
  function show(id) {
    cur = id;
    page.querySelectorAll('.ae-tab').forEach(b => b.classList.toggle('active', b.dataset.ae === id));
    Object.keys(secs).forEach(k => secs[k].classList.toggle('active', k === id));
  }
  function load() { if (cur === 'admin-events') loadEvents(true); else loadAdminCategories(); }
  page.querySelector('.ae-tabs').addEventListener('click', e => { const b = e.target.closest('.ae-tab'); if (!b) return; show(b.dataset.ae); load(); });
  nav.addEventListener('click', () => setTimeout(() => {
    const t = document.querySelector('.page-title'), s = document.querySelector('.page-subtitle');
    if (t) t.textContent = 'Admin Events'; if (s) s.textContent = 'Manage events and event categories';
    show(cur); loadEvents(true); loadAdminCategories();
  }, 0));
})();
