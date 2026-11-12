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
  .yd-back{position:fixed;inset:0;z-index:10000;background:rgba(8,16,31,.6);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:16px}
  .yd-box{background:#fff;border-radius:18px;width:100%;max-width:460px;padding:22px;box-shadow:0 24px 70px rgba(0,0,0,.4);border-top:4px solid var(--crimson);max-height:92vh;max-height:92dvh;overflow:auto}
  .yd-ic{width:48px;height:48px;border-radius:14px;background:var(--crimson-pale);color:var(--crimson);display:grid;place-items:center;font-size:20px;margin-bottom:12px}
  .yd-box h4{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;margin-bottom:6px;color:var(--text-primary)}
  .yd-box p{font-size:13px;color:var(--text-soft);line-height:1.55;margin-bottom:12px}
  .yd-box p b{color:var(--text-primary)}
  .yd-names{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
  .yd-names span{background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:99px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .yd-box label{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--text-soft);margin-bottom:6px}
  .yd-box label b{color:var(--crimson)}
  .yd-box input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:12px 13px;font-size:16px;font-weight:700;letter-spacing:2px;outline:none;text-transform:uppercase;min-height:48px}
  .yd-box input:focus{border-color:var(--crimson);box-shadow:0 0 0 3px rgba(220,38,38,.12)}
  .yd-box input.ok{border-color:var(--emerald);box-shadow:0 0 0 3px rgba(5,150,105,.12)}
  .yd-act{display:flex;gap:10px;margin-top:16px}
  .yd-act button{flex:1;min-height:48px;border-radius:10px;font-size:14px;font-weight:700;border:1.5px solid var(--border);background:#fff;color:var(--text-mid)}
  .yd-act button.go{background:var(--crimson);border-color:var(--crimson);color:#fff}
  .yd-act button:disabled{opacity:.45;cursor:not-allowed}
  @media (max-width:640px){
    .yd-back{align-items:flex-end;padding:0}
    .yd-box{max-width:none;border-radius:24px 24px 0 0;padding:22px 18px calc(22px + env(safe-area-inset-bottom));animation:ydUp .26s cubic-bezier(.2,.8,.2,1)}
    @keyframes ydUp{from{transform:translateY(46px);opacity:0}to{transform:none;opacity:1}}
  }`;
  document.head.appendChild(st);

  /* In-page confirmation window (replaces the browser's prompt box). Resolves true only when DELETE is typed. */
  function askDelete(count, names) {
    return new Promise(resolve => {
      const back = document.createElement('div');
      back.className = 'yd-back';
      const shown = names.slice(0, 6).map(n => '<span>' + escapeHtml(n) + '</span>').join('');
      const more = names.length > 6 ? '<span>+' + (names.length - 6) + ' more</span>' : '';
      back.innerHTML = '<div class="yd-box" role="dialog" aria-modal="true" aria-label="Confirm bulk delete">' +
        '<div class="yd-ic"><i class="fas fa-trash-can"></i></div>' +
        '<h4>Delete ' + count + ' member(s)?</h4>' +
        '<p>They and <b>all their attendance</b> will be moved to the <b>Recycle Bin</b>. You can restore them within 30 days.</p>' +
        '<div class="yd-names">' + shown + more + '</div>' +
        '<label>Type <b>DELETE</b> to confirm</label>' +
        '<input type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="DELETE"/>' +
        '<div class="yd-act"><button type="button" class="cancel">Cancel</button><button type="button" class="go" disabled><i class="fas fa-trash"></i> Delete</button></div></div>';
      document.body.appendChild(back);
      const inp = back.querySelector('input'), go = back.querySelector('.go');
      setTimeout(() => inp.focus(), 60);
      const valid = () => inp.value.trim().toUpperCase() === 'DELETE';
      const done = v => { document.removeEventListener('keydown', onKey, true); back.remove(); resolve(v); };
      const onKey = e => {
        if (e.key === 'Escape') { e.preventDefault(); done(false); }
        else if (e.key === 'Enter' && valid()) { e.preventDefault(); done(true); }
      };
      document.addEventListener('keydown', onKey, true);
      inp.addEventListener('input', () => { const v = valid(); go.disabled = !v; inp.classList.toggle('ok', v); });
      go.addEventListener('click', () => { if (valid()) done(true); });
      back.querySelector('.cancel').addEventListener('click', () => done(false));
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
