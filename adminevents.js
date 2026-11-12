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

/* ---------- Event Categories: BULK DELETE (button left of the trash button, Secretariat only) ---------- */
(function () {
  const box = document.getElementById('categoriesList');
  if (!box || typeof loadAdminCategories !== 'function') return;

  const st = document.createElement('style');
  st.textContent = `.bk-btn:hover{background:var(--crimson)!important;color:#fff!important;border-color:var(--crimson)!important}
  .bk-btn.on{background:var(--crimson-pale)!important;color:var(--crimson)!important;border-color:var(--crimson)!important}
  .bk-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#FEF2F2;border:1.5px solid #FCA5A5;border-radius:var(--radius-sm);padding:10px 14px;margin:0 0 10px}
  .bk-bar .bk-count{font-size:13px;font-weight:800;color:#991B1B;flex:1;min-width:140px}
  .bk-bar button{border:none;border-radius:8px;padding:9px 14px;font-size:12.5px;font-weight:700;min-height:38px}
  .bk-bar .bk-del{background:var(--crimson);color:#fff}
  .bk-bar .bk-del:disabled{opacity:.45;cursor:not-allowed}
  .bk-bar .bk-cancel{background:#fff;color:var(--text-mid);border:1.5px solid var(--border)}
  .bk-th,.bk-td{width:44px}
  .bk-th input,.bk-td input{width:18px;height:18px;accent-color:var(--crimson);cursor:pointer}
  tr.bk-sel td{background:#FEF2F2!important}`;
  document.head.appendChild(st);

  const SEL = {}; // categoryId -> Set of selected event ids (key present = bulk mode is on for that category)

  function evId(row) { const b = row.querySelector('.cat-ev-edit'); return b ? String(b.dataset.id) : null; }

  function refreshBar(block) {
    const cid = block.dataset.cid, set = SEL[cid];
    if (!set) return;
    const cbs = block.querySelectorAll('.bk-cb');
    const n = set.size;
    const cnt = block.querySelector('.bk-count'), del = block.querySelector('.bk-del'), all = block.querySelector('.bk-all');
    if (cnt) cnt.textContent = n ? n + ' of ' + cbs.length + ' event(s) selected' : 'Tick the events you want to delete';
    if (del) { del.disabled = !n; del.innerHTML = '<i class="fas fa-trash"></i> Delete selected' + (n ? ' (' + n + ')' : ''); }
    if (all) { all.checked = n > 0 && n === cbs.length; all.indeterminate = n > 0 && n < cbs.length; }
  }

  function decorate() {
    box.querySelectorAll('.cat-block').forEach(block => {
      const del = block.querySelector('.cat-delete');
      if (!del) return; // the Default (Normal Serve) category has no events to bulk delete
      const cid = String(del.dataset.id);
      const table = block.querySelector('table.data-table');
      const rows = table ? Array.from(table.querySelectorAll('tbody tr')).filter(r => evId(r)) : [];
      if (!rows.length) { delete SEL[cid]; return; }
      block.dataset.cid = cid;

      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'table-btn delete-btn bk-btn' + (SEL[cid] ? ' on' : '');
      b.dataset.bulk = cid;
      b.title = 'Bulk delete events';
      b.setAttribute('aria-label', 'Bulk delete events');
      b.innerHTML = '<i class="fas fa-list-check"></i>';
      del.before(b);

      if (!SEL[cid]) return;
      const present = new Set(rows.map(evId));
      SEL[cid] = new Set(Array.from(SEL[cid]).filter(x => present.has(x)));

      table.querySelector('thead tr').insertAdjacentHTML('afterbegin', '<th class="bk-th"><input type="checkbox" class="bk-all" aria-label="Select all events"/></th>');
      rows.forEach(r => {
        const id = evId(r), on = SEL[cid].has(id);
        r.insertAdjacentHTML('afterbegin', '<td class="bk-td"><input type="checkbox" class="bk-cb" value="' + id + '"' + (on ? ' checked' : '') + ' aria-label="Select event"/></td>');
        r.classList.toggle('bk-sel', on);
      });
      const bar = document.createElement('div');
      bar.className = 'bk-bar';
      bar.innerHTML = '<span class="bk-count"></span><button type="button" class="bk-del" disabled><i class="fas fa-trash"></i> Delete selected</button><button type="button" class="bk-cancel">Cancel</button>';
      table.closest('.table-wrap').before(bar);
      refreshBar(block);
    });
  }

  const _lac = loadAdminCategories;
  loadAdminCategories = async function () { await _lac.apply(this, arguments); try { decorate(); } catch (e) { console.error(e); } };

  async function bulkApi(ids) {
    try {
      const r = await fetch('api_bulkdelete.php?action=bulk_delete_events', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids }) });
      return await r.json();
    } catch (e) { return { success: false, message: 'Network error. Please check your connection.' }; }
  }

  box.addEventListener('click', async e => {
    const tog = e.target.closest('[data-bulk]');
    if (tog) {
      const cid = String(tog.dataset.bulk);
      if (SEL[cid]) delete SEL[cid]; else SEL[cid] = new Set();
      loadAdminCategories();
      return;
    }
    const block = e.target.closest('.cat-block');
    if (!block) return;
    const cid = block.dataset.cid;
    if (e.target.closest('.bk-cancel')) { delete SEL[cid]; loadAdminCategories(); return; }
    const del = e.target.closest('.bk-del');
    if (del && SEL[cid] && SEL[cid].size) {
      const ids = Array.from(SEL[cid]);
      if (!confirm('Delete ' + ids.length + ' selected event(s)?\n\nTheir attendance records, servers and leaders will also be permanently deleted. This cannot be undone.')) return;
      del.disabled = true;
      const res = await bulkApi(ids);
      alert(res.message);
      if (res.success) delete SEL[cid];
      try { await loadAdminCategories(); } catch (_) {}
      try { if (typeof loadEvents === 'function') loadEvents(true); } catch (_) {}
      try { if (typeof loadDashboardStats === 'function') loadDashboardStats(); } catch (_) {}
    }
  });

  box.addEventListener('change', e => {
    const block = e.target.closest('.cat-block');
    if (!block) return;
    const cid = block.dataset.cid, set = SEL[cid];
    if (!set) return;
    if (e.target.classList.contains('bk-all')) {
      block.querySelectorAll('.bk-cb').forEach(cb => {
        cb.checked = e.target.checked;
        cb.closest('tr').classList.toggle('bk-sel', cb.checked);
        if (cb.checked) set.add(cb.value); else set.delete(cb.value);
      });
      refreshBar(block);
    } else if (e.target.classList.contains('bk-cb')) {
      if (e.target.checked) set.add(e.target.value); else set.delete(e.target.value);
      e.target.closest('tr').classList.toggle('bk-sel', e.target.checked);
      refreshBar(block);
    }
  });
})();

/* ---------- Youth Members: BULK DELETE → Recycle Bin (Secretariat only) ---------- */
/* Members that belong to a user account (Slot Leaders and Admin/Secretariat) have NO checkbox:
   they can only be deleted from Users & Roles. */
(function () {
  const st = document.createElement('style');
  st.textContent = `.yb-toggle{display:inline-flex;align-items:center;gap:7px;border:1.5px solid var(--border);background:var(--surface);color:var(--text-mid);font-size:13px;font-weight:700;padding:9px 14px;border-radius:var(--radius-sm)}
  .yb-toggle:hover,.yb-toggle.on{background:var(--crimson-pale);color:var(--crimson);border-color:var(--crimson)}
  .yb-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#FEF2F2;border:1.5px solid #FCA5A5;border-radius:var(--radius-sm);padding:10px 14px;margin:0 0 12px}
  .yb-bar .yb-count{font-size:13px;font-weight:800;color:#991B1B;flex:1;min-width:160px}
  .yb-bar .yb-hint{flex:1 1 100%;font-size:11.5px;color:#B91C1C;font-weight:600;order:10}
  .yb-bar label{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;color:#991B1B;cursor:pointer}
  .yb-bar button{border:none;border-radius:8px;padding:9px 14px;font-size:12.5px;font-weight:700;min-height:38px}
  .yb-bar .yb-del{background:var(--crimson);color:#fff}
  .yb-bar .yb-del:disabled{opacity:.45;cursor:not-allowed}
  .yb-bar .yb-cancel{background:#fff;color:var(--text-mid);border:1.5px solid var(--border)}
  .yb-cb{width:18px;height:18px;accent-color:var(--crimson);cursor:pointer;margin-right:10px;vertical-align:middle;flex:none}
  .yb-lock{display:inline-block;width:18px;margin-right:10px;text-align:center;font-size:12px;color:var(--text-faint);vertical-align:middle;cursor:help}
  .yb-bar input{width:18px;height:18px;accent-color:var(--crimson)}
  tr.yb-sel td{background:#FEF2F2!important}

  /* ---- Bulk delete confirmation window ---- */
  .yd-back{position:fixed;inset:0;z-index:10000;background:rgba(7,13,38,.62);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:16px;animation:ydFade .18s ease-out}
  .yd-box{position:relative;background:#fff;border-radius:22px;width:100%;max-width:440px;max-height:92vh;max-height:92dvh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 0 0 1px rgba(8,16,31,.06),0 30px 80px -12px rgba(8,16,31,.55);animation:ydPop .24s cubic-bezier(.2,.9,.3,1.1)}
  .yd-box::before{content:'';position:absolute;left:0;right:0;top:0;height:4px;background:linear-gradient(90deg,#DC2626,#F87171 55%,#DC2626)}
  .yd-pane{display:flex;flex-direction:column;min-height:0;flex:1}
  .yd-pane[hidden]{display:none}
  .yd-body{padding:30px 26px 6px;overflow:auto}
  .yd-head{display:flex;gap:16px;align-items:flex-start;margin-bottom:20px}
  .yd-ic{flex:none;width:52px;height:52px;border-radius:16px;display:grid;place-items:center;font-size:21px;color:#DC2626;background:linear-gradient(160deg,#FEE2E2,#FECACA);box-shadow:inset 0 0 0 1px rgba(220,38,38,.18),0 8px 18px -8px rgba(220,38,38,.55)}
  .yd-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:21px;font-weight:800;letter-spacing:-.4px;line-height:1.2;color:#0C1629;margin:2px 0 5px}
  .yd-sub{font-size:13.5px;line-height:1.5;color:#56688C;margin:0}
  .yd-facts{list-style:none;margin:0 0 14px;padding:4px 16px;background:#F7F9FF;border:1px solid #E2E8F4;border-radius:14px}
  .yd-facts li{display:flex;gap:12px;align-items:flex-start;padding:12px 0;font-size:13px;line-height:1.45;color:#3D4F6E}
  .yd-facts li+li{border-top:1px solid #E2E8F4}
  .yd-facts i{flex:none;width:18px;margin-top:2px;text-align:center;font-size:13px}
  .yd-facts b{color:#0C1629;font-weight:700}
  .yd-facts .i-bin{color:#D97706}.yd-facts .i-clock{color:#2563EB}.yd-facts .i-lock{color:#059669}
  .yd-view{width:100%;display:flex;align-items:center;gap:12px;text-align:left;border:1.5px solid #E2E8F4;background:#fff;border-radius:14px;padding:12px 14px;margin:0 0 22px;font-size:13.5px;font-weight:700;color:#0C1629;cursor:pointer;transition:border-color .18s,box-shadow .18s,background .18s}
  .yd-view:hover{border-color:#93B4FF;background:#F5F8FF;box-shadow:0 6px 18px -10px rgba(37,99,235,.5)}
  .yd-view .yd-av{flex:none;display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#EFF6FF;color:#1D4ED8;font-size:14px}
  .yd-view span{flex:1;min-width:0}
  .yd-view small{display:block;font-weight:500;font-size:12px;color:#6B7FA8;margin-top:1px}
  .yd-view .yd-chev{color:#A0AEC4;font-size:12px;transition:transform .18s}
  .yd-view:hover .yd-chev{transform:translateX(3px);color:#2563EB}
  .yd-label{display:block;font-size:13px;font-weight:700;color:#0C1629;margin:0 0 8px}
  .yd-label b{display:inline-block;background:#FEE2E2;color:#B91C1C;border-radius:6px;padding:1px 7px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;letter-spacing:.5px}
  .yd-input{width:100%;min-height:52px;border:1.5px solid #E2E8F4;border-radius:14px;padding:12px 16px;font-size:17px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#0C1629;background:#fff;outline:none;transition:border-color .18s,box-shadow .18s,background .18s}
  .yd-input::placeholder{color:#C3CCDD;letter-spacing:3px}
  .yd-input:focus{border-color:#DC2626;box-shadow:0 0 0 4px rgba(220,38,38,.12)}
  .yd-input.ok{border-color:#059669;background:#F0FDF9;box-shadow:0 0 0 4px rgba(5,150,105,.12)}
  .yd-foot{display:flex;gap:10px;padding:20px 26px 26px}
  .yd-foot button{flex:1;min-height:50px;border-radius:14px;font-size:14.5px;font-weight:700;border:1.5px solid #E2E8F4;background:#fff;color:#3D4F6E;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:background .18s,box-shadow .18s,transform .12s,opacity .18s}
  .yd-foot button:hover:not(:disabled){background:#F7F9FF}
  .yd-foot button:active:not(:disabled){transform:scale(.98)}
  .yd-foot .go{background:linear-gradient(180deg,#EF4444,#DC2626);border-color:#DC2626;color:#fff;box-shadow:0 10px 22px -8px rgba(220,38,38,.65)}
  .yd-foot .go:hover:not(:disabled){background:linear-gradient(180deg,#F05252,#D52424)}
  .yd-foot .go:disabled{opacity:.4;box-shadow:none;cursor:not-allowed}
  .yd-foot .cancel{flex:0 0 38%}

  /* names pane */
  .yd-nh{display:flex;align-items:center;gap:12px;padding:24px 22px 14px}
  .yd-back-btn{flex:none;width:40px;height:40px;border-radius:12px;border:1.5px solid #E2E8F4;background:#fff;color:#3D4F6E;font-size:14px;cursor:pointer;display:grid;place-items:center;transition:background .18s}
  .yd-back-btn:hover{background:#F7F9FF}
  .yd-nh h4{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;letter-spacing:-.3px;color:#0C1629;margin:0;line-height:1.2}
  .yd-nh p{font-size:12.5px;color:#6B7FA8;margin:2px 0 0}
  .yd-sbox{position:relative;margin:0 22px 12px}
  .yd-sbox>i.fa-search{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#A0AEC4;font-size:13px;pointer-events:none}
  .yd-sbox input{width:100%;min-height:46px;border:1.5px solid #E2E8F4;border-radius:12px;padding:10px 40px 10px 40px;font-size:15px;outline:none;color:#0C1629;background:#F7F9FF;transition:border-color .18s,box-shadow .18s,background .18s}
  .yd-sbox input:focus{border-color:#3B82F6;background:#fff;box-shadow:0 0 0 4px rgba(59,130,246,.12)}
  .yd-sbox .yd-clr{position:absolute;right:7px;top:50%;transform:translateY(-50%);width:32px;height:32px;border:none;background:none;color:#6B7FA8;border-radius:50%;cursor:pointer;font-size:13px}
  .yd-sbox .yd-clr:hover{background:#E8EEFF}
  .yd-sbox .yd-clr[hidden]{display:none}
  .yd-list{flex:1;min-height:160px;max-height:min(380px,50vh);overflow:auto;margin:0 12px;padding:0 10px 4px;list-style:none;overscroll-behavior:contain}
  .yd-list li{display:flex;align-items:center;gap:12px;padding:9px 8px;border-radius:12px;font-size:14px;font-weight:600;color:#0C1629}
  .yd-list li:hover{background:#F5F8FF}
  .yd-list .n{flex:none;min-width:30px;text-align:right;font-size:11.5px;font-weight:700;color:#A0AEC4;font-variant-numeric:tabular-nums}
  .yd-list .a{flex:none;width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:800;color:#1D4ED8;background:linear-gradient(160deg,#EFF6FF,#DBEAFE);box-shadow:inset 0 0 0 1px rgba(37,99,235,.15)}
  .yd-list .t{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .yd-list mark{background:#FEF3C7;color:inherit;border-radius:4px;padding:0 1px}
  .yd-empty{display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;padding:40px 20px;color:#6B7FA8;font-size:13.5px}
  .yd-empty i{font-size:26px;color:#CBD5E8;margin-bottom:4px}
  .yd-empty b{color:#0C1629}
  .yd-count{padding:10px 26px 0;font-size:12.5px;color:#6B7FA8}
  @keyframes ydFade{from{opacity:0}to{opacity:1}}
  @keyframes ydPop{from{transform:translateY(14px) scale(.97);opacity:0}to{transform:none;opacity:1}}
  @media (max-width:640px){
    .yd-back{align-items:flex-end;padding:0}
    .yd-box{max-width:none;border-radius:26px 26px 0 0;animation:ydUp .28s cubic-bezier(.2,.8,.2,1)}
    .yd-body{padding:28px 20px 4px}
    .yd-foot{padding:16px 20px calc(20px + env(safe-area-inset-bottom))}
    .yd-nh{padding:22px 16px 12px}
    .yd-sbox{margin:0 16px 10px}
    .yd-count{padding:8px 20px 0}
    .yd-list{max-height:48vh}
    @keyframes ydUp{from{transform:translateY(60px);opacity:0}to{transform:none;opacity:1}}
  }
  @media (prefers-reduced-motion:reduce){.yd-back,.yd-box{animation:none!important}}`;
  document.head.appendChild(st);

  const esc = s => escapeHtml(s);
  const initials = n => {
    const p = String(n).replace(/[^\p{L}\s,]/gu, '').split(/[\s,]+/).filter(Boolean);
    return ((p[0] || '?').charAt(0) + (p.length > 1 ? p[1].charAt(0) : '')).toUpperCase();
  };
  const hi = (name, q) => {
    if (!q) return esc(name);
    const i = name.toLowerCase().indexOf(q);
    if (i < 0) return esc(name);
    return esc(name.slice(0, i)) + '<mark>' + esc(name.slice(i, i + q.length)) + '</mark>' + esc(name.slice(i + q.length));
  };

  /* In-page confirmation window. Resolves true only when DELETE is typed.
     Main screen shows a clean summary; "View selected names" opens a searchable list. */
  function askDelete(count, names) {
    return new Promise(resolve => {
      const back = document.createElement('div');
      back.className = 'yd-back';
      back.innerHTML =
        '<div class="yd-box" role="dialog" aria-modal="true" aria-labelledby="ydTitle">' +
          '<div class="yd-pane" data-pane="main">' +
            '<div class="yd-body">' +
              '<div class="yd-head"><div class="yd-ic"><i class="fas fa-trash-can"></i></div>' +
                '<div><h4 class="yd-title" id="ydTitle">Delete ' + count + ' member' + (count === 1 ? '' : 's') + '?</h4>' +
                '<p class="yd-sub">This removes them from Youth Members. Nothing is lost for good.</p></div></div>' +
              '<ul class="yd-facts">' +
                '<li><i class="fas fa-box-archive i-bin"></i><span>Moved to the <b>Recycle Bin</b> together with <b>all their attendance</b>.</span></li>' +
                '<li><i class="fas fa-clock-rotate-left i-clock"></i><span>You can <b>restore them within 30 days</b> from Admin Panel.</span></li>' +
              '</ul>' +
              '<button type="button" class="yd-view"><span class="yd-av"><i class="fas fa-users"></i></span><span>View selected names<small>Review and search the ' + count + ' member' + (count === 1 ? '' : 's') + '</small></span><i class="fas fa-chevron-right yd-chev"></i></button>' +
              '<label class="yd-label" for="ydInput">Type <b>DELETE</b> to confirm</label>' +
              '<input id="ydInput" class="yd-input" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="DELETE"/>' +
            '</div>' +
            '<div class="yd-foot"><button type="button" class="cancel">Cancel</button><button type="button" class="go" disabled><i class="fas fa-trash"></i> Delete ' + count + '</button></div>' +
          '</div>' +
          '<div class="yd-pane" data-pane="names" hidden>' +
            '<div class="yd-nh"><button type="button" class="yd-back-btn" aria-label="Back"><i class="fas fa-arrow-left"></i></button>' +
              '<div><h4>Selected members</h4><p>' + count + ' member' + (count === 1 ? '' : 's') + ' will be deleted</p></div></div>' +
            '<div class="yd-sbox"><i class="fas fa-search"></i><input type="text" placeholder="Search a name" autocomplete="off" spellcheck="false" aria-label="Search selected names"/><button type="button" class="yd-clr" aria-label="Clear search" hidden><i class="fas fa-times"></i></button></div>' +
            '<div class="yd-count" aria-live="polite"></div>' +
            '<ul class="yd-list"></ul>' +
            '<div class="yd-foot"><button type="button" class="back2">Back to confirm</button></div>' +
          '</div>' +
        '</div>';
      document.body.appendChild(back);

      const main = back.querySelector('[data-pane="main"]'), pn = back.querySelector('[data-pane="names"]');
      const inp = back.querySelector('#ydInput'), go = back.querySelector('.go');
      const sIn = pn.querySelector('.yd-sbox input'), clr = pn.querySelector('.yd-clr'), list = pn.querySelector('.yd-list'), cnt = pn.querySelector('.yd-count');
      setTimeout(() => inp.focus(), 80);

      const valid = () => inp.value.trim().toUpperCase() === 'DELETE';
      const done = v => { document.removeEventListener('keydown', onKey, true); back.remove(); resolve(v); };
      const inNames = () => !pn.hidden;

      function drawList() {
        const q = sIn.value.trim().toLowerCase();
        clr.hidden = !q;
        const rows = [];
        names.forEach((n, i) => { if (!q || n.toLowerCase().includes(q)) rows.push([n, i + 1]); });
        cnt.textContent = q ? rows.length + ' of ' + names.length + (rows.length === 1 ? ' match' : ' matches') : 'Showing all ' + names.length;
        list.innerHTML = rows.length
          ? rows.map(([n, i]) => '<li><span class="n">' + i + '</span><span class="a">' + esc(initials(n)) + '</span><span class="t" title="' + esc(n) + '">' + hi(n, q) + '</span></li>').join('')
          : '<li class="yd-empty" style="display:flex"><i class="fas fa-user-slash"></i><span><b>No match</b> for “' + esc(sIn.value.trim()) + '”</span><span>Check the spelling or clear the search.</span></li>';
        list.scrollTop = 0;
      }
      function openNames() {
        main.hidden = true; pn.hidden = false;
        sIn.value = ''; drawList();
        setTimeout(() => sIn.focus(), 40);
      }
      function closeNames() {
        pn.hidden = true; main.hidden = false;
        setTimeout(() => inp.focus(), 40);
      }

      function onKey(e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          if (inNames()) { if (sIn.value) { sIn.value = ''; drawList(); } else closeNames(); } else done(false);
        } else if (e.key === 'Enter' && !inNames() && valid()) { e.preventDefault(); done(true); }
      }
      document.addEventListener('keydown', onKey, true);

      inp.addEventListener('input', () => { const v = valid(); go.disabled = !v; inp.classList.toggle('ok', v); });
      go.addEventListener('click', () => { if (valid()) done(true); });
      back.querySelector('.cancel').addEventListener('click', () => done(false));
      back.querySelector('.yd-view').addEventListener('click', openNames);
      pn.querySelector('.yd-back-btn').addEventListener('click', closeNames);
      pn.querySelector('.back2').addEventListener('click', closeNames);
      sIn.addEventListener('input', drawList);
      clr.addEventListener('click', () => { sIn.value = ''; drawList(); sIn.focus(); });
      back.addEventListener('mousedown', e => { if (e.target === back) done(false); });
    });
  }

  let ON = false;
  const SEL = new Set();
  let LOCKED = new Set(); // youth ids that belong to a leader / admin account
  const $ = id => document.getElementById(id);

  async function loadLocked() {
    try {
      const r = await fetch('api_bulkyouth.php?action=locked_ids', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
      const j = await r.json();
      LOCKED = new Set(j.success && Array.isArray(j.data) ? j.data.map(String) : []);
    } catch (e) { LOCKED = new Set(); }
  }

  function visibleRows() {
    return Array.from(document.querySelectorAll('#youthTableBody tr')).filter(r => r.querySelector('.yb-cb') && r.style.display !== 'none');
  }
  function refreshBar() {
    const bar = $('ybBar');
    if (!bar) return;
    const vis = visibleRows();
    const vSel = vis.filter(r => SEL.has(r.querySelector('.yb-cb').value)).length;
    bar.querySelector('.yb-count').textContent = SEL.size ? SEL.size + ' member(s) selected' : 'Tick the members you want to delete';
    const del = bar.querySelector('.yb-del');
    del.disabled = !SEL.size;
    del.innerHTML = '<i class="fas fa-trash"></i> Delete selected' + (SEL.size ? ' (' + SEL.size + ')' : '');
    const all = bar.querySelector('.yb-all');
    all.checked = vis.length > 0 && vSel === vis.length;
    all.indeterminate = vSel > 0 && vSel < vis.length;
  }
  function removeBar() { const b = $('ybBar'); if (b) b.remove(); }

  function decorate() {
    const btn = $('ybToggle');
    if (btn) btn.classList.toggle('on', ON);
    removeBar();
    if (!ON) return;
    const tb = $('youthTableBody');
    if (!tb) return;
    const present = new Set();
    Array.from(tb.querySelectorAll('tr')).forEach(r => {
      const d = r.querySelector('.delete-btn[data-id]');
      if (!d) return;
      const id = String(d.dataset.id);
      const td = r.cells[0];
      if (LOCKED.has(id)) {
        // Leader / Admin: no checkbox, can only be deleted in Users & Roles
        SEL.delete(id);
        if (!td.querySelector('.yb-lock')) td.insertAdjacentHTML('afterbegin', '<i class="fas fa-lock yb-lock" title="Leader / Admin: delete this account in Users &amp; Roles"></i>');
        return;
      }
      present.add(id);
      if (td.querySelector('.yb-cb')) return;
      td.insertAdjacentHTML('afterbegin', '<input type="checkbox" class="yb-cb" value="' + id + '"' + (SEL.has(id) ? ' checked' : '') + ' aria-label="Select member"/>');
      r.classList.toggle('yb-sel', SEL.has(id));
    });
    Array.from(SEL).forEach(id => { if (!present.has(id)) SEL.delete(id); });
    const bar = document.createElement('div');
    bar.id = 'ybBar';
    bar.className = 'yb-bar';
    bar.innerHTML = '<label><input type="checkbox" class="yb-all"/> Select all shown</label><span class="yb-count"></span><button type="button" class="yb-del" disabled><i class="fas fa-trash"></i> Delete selected</button><button type="button" class="yb-cancel">Cancel</button><span class="yb-hint"><i class="fas fa-lock"></i> Leaders and admins are locked. Delete them in Users &amp; Roles.</span>';
    tb.closest('.table-wrap').before(bar);
    refreshBar();
  }

  async function bulkApi(ids) {
    try {
      const r = await fetch('api_bulkyouth.php?action=bulk_delete_youth', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids }) });
      return await r.json();
    } catch (e) { return { success: false, message: 'Network error. Please check your connection.' }; }
  }

  function stop() { ON = false; SEL.clear(); }

  document.addEventListener('DOMContentLoaded', async () => {
    if (typeof apiCall !== 'function' || typeof loadYouth !== 'function') return;
    const s = await apiCall('check_session');
    if (!(s.success && s.data.role === 'secretariat')) return;
    const slot = $('youthSlotFilter');
    const tools = slot ? slot.parentElement : null;
    if (!tools) return;

    const btn = document.createElement('button');
    btn.type = 'button'; btn.id = 'ybToggle'; btn.className = 'yb-toggle';
    btn.title = 'Bulk delete members (goes to the Recycle Bin)';
    btn.innerHTML = '<i class="fas fa-list-check"></i> Bulk delete';
    tools.appendChild(btn);
    btn.addEventListener('click', () => { if (ON) stop(); else { ON = true; SEL.clear(); } loadYouth(); });

    const _ly = loadYouth;
    loadYouth = async function () {
      await _ly.apply(this, arguments);
      try { if (ON) await loadLocked(); decorate(); } catch (e) { console.error(e); }
    };

    // Search / filters hide rows: keep the counter and "select all" correct
    ['youthSearch', 'youthSlotFilter', 'youthScholarFilter'].forEach(id => {
      const el = $(id);
      if (el) el.addEventListener(id === 'youthSearch' ? 'input' : 'change', () => setTimeout(refreshBar, 0));
    });

    const tb = $('youthTableBody');
    tb.addEventListener('change', e => {
      const c = e.target.closest('.yb-cb');
      if (!c) return;
      if (LOCKED.has(String(c.value))) { c.checked = false; return; }
      if (c.checked) SEL.add(c.value); else SEL.delete(c.value);
      c.closest('tr').classList.toggle('yb-sel', c.checked);
      refreshBar();
    });

    const page = $('page-youth');
    page.addEventListener('change', e => {
      if (!e.target.classList.contains('yb-all')) return;
      visibleRows().forEach(r => {
        const cb = r.querySelector('.yb-cb');
        if (LOCKED.has(String(cb.value))) return;
        cb.checked = e.target.checked;
        r.classList.toggle('yb-sel', cb.checked);
        if (cb.checked) SEL.add(cb.value); else SEL.delete(cb.value);
      });
      refreshBar();
    });
    page.addEventListener('click', async e => {
      if (e.target.closest('.yb-cancel')) { stop(); loadYouth(); return; }
      const del = e.target.closest('.yb-del');
      if (!del || !SEL.size) return;
      const ids = Array.from(SEL).filter(id => !LOCKED.has(String(id)));
      if (!ids.length) return;
      const names = Array.from(document.querySelectorAll('#youthTableBody tr'))
        .filter(r => { const c = r.querySelector('.yb-cb'); return c && SEL.has(c.value); })
        .map(r => r.cells[0].textContent.trim());
      const ok = await askDelete(ids.length, names);
      if (!ok) return;
      del.disabled = true;
      const res = await bulkApi(ids);
      alert(res.message);
      if (res.success) stop();
      try { await loadYouth(); } catch (_) {}
      try { if (typeof loadDashboardStats === 'function') loadDashboardStats(); } catch (_) {}
    });
  });
})();
