/* PYM — Events: Type = Event Category, "needs servers" checkbox + typed server names, and "multiple slots" (pick several dates for one event). Loaded last on index.php. */
(function () {
  const css = `
  .srv-box{margin:4px 0 14px}
  .srv-check{display:flex;align-items:center;gap:10px;font-size:13.5px;font-weight:700;color:var(--text-primary);cursor:pointer;padding:6px 0}
  .srv-check input{width:18px;height:18px;accent-color:var(--sapphire)}
  .srv-check input:disabled+span{opacity:.5}
  .srv-panel{border:1.5px dashed var(--border-mid);border-radius:var(--radius-sm);padding:14px;background:var(--surface);margin-top:6px}
  .srv-note{font-size:12px;color:var(--text-soft);margin-bottom:10px;line-height:1.5}
  .srv-search{position:relative;max-width:420px}
  .srv-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px}
  .srv-search input{width:100%;padding:10px 12px 10px 34px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:13px;outline:none}
  .srv-search input:focus{border-color:var(--sapphire-glow);box-shadow:0 0 0 3px rgba(59,130,246,.10)}
  .srv-list{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:30;max-height:230px;overflow-y:auto;background:var(--surface);border:1.5px solid var(--border-mid);border-radius:8px;box-shadow:var(--shadow-md);padding:4px}
  .srv-opt{padding:8px 10px;font-size:13px;border-radius:6px;cursor:pointer}
  .srv-opt:hover{background:var(--sapphire-pale)}
  .srv-none{padding:9px 10px;font-size:12px;color:var(--text-faint);font-style:italic}
  .srv-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
  .srv-chip{display:inline-flex;align-items:center;gap:6px;background:var(--sapphire-pale);border:1px solid #BFDBFE;color:var(--sapphire);font-size:12px;font-weight:700;padding:4px 6px 4px 11px;border-radius:99px}
  .srv-chip button{border:none;background:rgba(0,0,0,.08);width:18px;height:18px;border-radius:50%;font-size:11px;line-height:1;color:inherit}
  .srv-empty{font-size:11.5px;color:var(--text-faint);font-style:italic}
  .opt-row{display:flex;flex-wrap:wrap;align-items:center;gap:0 32px;margin:4px 0 14px}
  .opt-row .srv-box,.opt-row .mds-box{display:contents}
  .opt-row .srv-check{order:1}
  .opt-row .srv-panel{order:2;flex:0 0 100%}
  .ldr-row{border-left:4px solid transparent}
  .ldr-row.k-admin{border-left-color:#D97706;background:#FFFBEB}
  .ldr-row.k-leader{border-left-color:#2563EB;background:#EFF6FF}
  .ldr-row.k-youth{border-left-color:#059669;background:#ECFDF5}
  .ldr-row.k-admin small{background:#FEF3C7;color:#92400E}
  .ldr-row.k-leader small{background:#DBEAFE;color:#1D4ED8}
  .ldr-row.k-youth small{background:#D1FAE5;color:#047857}
  .ldr-row small{padding:2px 9px;border-radius:99px;font-weight:800}
  .ldr-h.h-acc{background:#DBEAFE;color:#1D4ED8}
  .ldr-h.h-youth{background:#D1FAE5;color:#047857}
  .srv-chip.c-admin{background:#FEF3C7;border-color:#FCD34D;color:#92400E}
  .srv-chip.c-leader{background:#DBEAFE;border-color:#93C5FD;color:#1D4ED8}
  .srv-chip.c-youth{background:#D1FAE5;border-color:#6EE7B7;color:#047857}
  .ldr-list{border:1.5px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);max-height:240px;overflow-y:auto;margin-top:10px;max-width:520px}
  .ldr-h{position:sticky;top:0;background:var(--bg);font-size:10.5px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--text-soft);padding:7px 12px;border-bottom:1px solid var(--border)}
  .ldr-row{display:flex;align-items:center;gap:10px;padding:8px 12px;font-size:13px;cursor:pointer;border-bottom:1px solid var(--border)}
  .ldr-row:last-child{border-bottom:none}
  .ldr-row:hover{background:var(--sapphire-pale)}
  .ldr-row input{width:17px;height:17px;accent-color:var(--sapphire);flex:none}
  .ldr-row small{color:var(--text-soft);margin-left:auto;font-size:11px}
  .mds-box{margin:4px 0 14px}
  .mds-panel{border:1.5px dashed var(--amber);border-radius:var(--radius-sm);padding:14px;background:#FFFBEB;margin-top:6px}
  .mds-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .mds-row input[type=date]{padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;outline:none;background:#fff}
  .mds-add{border:1.5px solid var(--amber);background:var(--amber-pale);color:var(--amber);font-size:12.5px;font-weight:800;padding:10px 16px;border-radius:var(--radius-sm)}
  .mds-add:hover{background:var(--amber);color:#fff}
  .mds-chip{background:var(--amber-pale);border-color:#FCD34D;color:#92400E}
  .mcal{background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);padding:14px;max-width:420px}
  .mcal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
  .mcal-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;color:var(--text-primary)}
  .mcal-nav button{width:34px;height:34px;border:1.5px solid var(--border);background:var(--surface);border-radius:8px;font-size:16px;font-weight:800;color:var(--text-mid)}
  .mcal-nav button:hover{background:var(--ink);color:#fff;border-color:var(--ink)}
  .mcal-dow,.mcal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}
  .mcal-dow div{text-align:center;font-size:10px;font-weight:800;letter-spacing:.8px;color:var(--text-faint);padding:6px 0}
  .mcal-day{aspect-ratio:1/1;min-height:40px;border:1.5px solid transparent;border-radius:10px;background:var(--sapphire-pale);font-size:14px;font-weight:700;color:var(--text-primary);transition:all .15s}
  .mcal-day:hover{border-color:var(--sapphire-glow)}
  .mcal-day.today{border-color:var(--amber)}
  .mcal-day.sel{background:var(--sapphire);color:#fff;border-color:var(--sapphire);box-shadow:var(--shadow-blue)}
  .mcal-blank{aspect-ratio:1/1;min-height:40px}
  .mcal-count{font-size:12px;font-weight:700;color:var(--text-soft);margin-top:10px}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  const $ = id => document.getElementById(id);
  const E = s => escapeHtml(s);
  let YOUTH = null;
  async function youth() {
    if (YOUTH) return YOUTH;
    const r = await coyApi('get_all_youth');
    YOUTH = r.success ? r.data : [];
    return YOUTH;
  }

  async function refreshCats() {
    const form = $('adminEventForm');
    const sel = form && form.elements['category_id'];
    if (!sel) return;
    const r = await apiCall('get_categories');
    const cats = r.success ? r.data.filter(c => Number(c.is_system) !== 1) : [];
    const cur = sel.value;
    sel.innerHTML = '<option value="">— Select category —</option>' + cats.map(c => `<option value="${c.id}">${E(c.name)}</option>`).join('');
    sel.value = cur;
  }

  /* ---------- "Needs servers" ---------- */
  function mount(form) {
    const box = document.createElement('div');
    box.className = 'srv-box';
    box.innerHTML = `
      <label class="srv-check"><input type="checkbox" class="srv-on"/> <span>This event needs servers</span></label>
      <div class="srv-panel hidden">
        <p class="srv-note">Type the names of the servers (no limit). Everyone else automatically appears as an attendee in Attendance, below the servers.</p>
        <div class="srv-search"><i class="fas fa-user-plus"></i><input type="text" class="srv-input" placeholder="Type a name..." autocomplete="off"/><div class="srv-list hidden"></div></div>
        <div class="srv-chips"></div>
      </div>`;
    form.querySelector('.form-actions').before(box);
    const on = box.querySelector('.srv-on'), panel = box.querySelector('.srv-panel'), inp = box.querySelector('.srv-input'),
          list = box.querySelector('.srv-list'), chips = box.querySelector('.srv-chips');
    const sel = new Map();

    const draw = () => {
      chips.innerHTML = sel.size
        ? [...sel].map(([id, n]) => `<span class="srv-chip">${E(n)}<button type="button" data-rm="${id}" aria-label="Remove">&times;</button></span>`).join('')
        : '<span class="srv-empty">No servers added yet.</span>';
    };
    const toggle = () => panel.classList.toggle('hidden', !on.checked);
    const show = async () => {
      const q = inp.value.trim().toLowerCase();
      const all = await youth();
      const items = all.filter(y => !sel.has(String(y.id)) && (!q || y.full_name.toLowerCase().includes(q))).slice(0, 40);
      list.innerHTML = items.length
        ? items.map(y => `<div class="srv-opt" data-id="${y.id}" data-name="${E(y.full_name)}">${E(y.full_name)}</div>`).join('')
        : '<div class="srv-none">No match</div>';
      list.classList.remove('hidden');
    };
    const add = o => { sel.set(String(o.dataset.id), o.dataset.name); inp.value = ''; list.classList.add('hidden'); draw(); inp.focus(); };

    on.addEventListener('change', toggle);
    inp.addEventListener('input', show);
    inp.addEventListener('focus', show);
    inp.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); const o = list.querySelector('.srv-opt'); if (o) add(o); }
      else if (e.key === 'Escape') list.classList.add('hidden');
    });
    list.addEventListener('mousedown', e => { const o = e.target.closest('.srv-opt'); if (o) { e.preventDefault(); add(o); } });
    chips.addEventListener('click', e => { const b = e.target.closest('[data-rm]'); if (b) { sel.delete(b.dataset.rm); draw(); } });
    document.addEventListener('mousedown', e => { if (!e.target.closest('.srv-search')) list.classList.add('hidden'); });

    const api = {
      box,
      get() { return { has_servers: on.checked ? 1 : 0, server_ids: on.checked ? [...sel.keys()] : [] }; },
      set(has, servers) {
        on.checked = !!has; sel.clear();
        (servers || []).forEach(s => sel.set(String(s.member_id), s.full_name));
        draw(); toggle();
      },
      reset() { on.checked = false; sel.clear(); inp.value = ''; list.classList.add('hidden'); draw(); toggle(); }
    };
    form.addEventListener('reset', () => setTimeout(api.reset, 0));
    draw();
    return api;
  }

  /* ---------- "Event leaders" (accounts + youth, checkbox list) ---------- */
  let ACCTS = null;
  async function accounts() {
    if (ACCTS) return ACCTS;
    try {
      const r = await fetch('api_eventleaders.php?action=users', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
      const j = await r.json();
      ACCTS = j.success ? j.data : [];
    } catch (e) { ACCTS = []; }
    return ACCTS;
  }
  async function leadersOf(eventId) {
    try {
      const r = await fetch('api_eventleaders.php?action=get', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event_id: eventId }) });
      const j = await r.json();
      return j.success ? j.data : { user_ids: [], youth_ids: [] };
    } catch (e) { return { user_ids: [], youth_ids: [] }; }
  }

  function mountLeaders(form, afterBox) {
    const box = document.createElement('div');
    box.className = 'srv-box';
    box.innerHTML = `
      <label class="srv-check"><input type="checkbox" class="ldr-on"/> <span>This event needs leaders</span></label>
      <div class="srv-panel hidden">
        <p class="srv-note">Tick the leaders for this event. Leader accounts can add members / mark attendance for <strong>this event only</strong>, until the event is done &mdash; then their access is removed, but the attendance they saved stays. Youth get access only if they have an account with the same name. Admin/Secretariat is never limited.</p>
        <div class="srv-search"><i class="fas fa-search"></i><input type="text" class="ldr-input" placeholder="Search a leader or youth..." autocomplete="off"/></div>
        <div class="ldr-list"></div>
        <div class="mcal-count ldr-count"></div>
        <div class="srv-chips"></div>
      </div>`;
    afterBox.after(box);
    const on = box.querySelector('.ldr-on'), panel = box.querySelector('.srv-panel'), inp = box.querySelector('.ldr-input'),
          list = box.querySelector('.ldr-list'), chips = box.querySelector('.srv-chips'), count = box.querySelector('.ldr-count');
    const U = new Map(), Y = new Map(); // id -> name
    let allU = [], allY = [];

    const drawChips = () => {
      const n = U.size + Y.size;
      count.textContent = n ? n + ' leader(s) selected' : 'No leaders selected yet.';
      chips.innerHTML =
        [...U].map(([id, nm]) => { const a = allU.find(x => String(x.id) === id); const ad = a && a.role === 'secretariat';
          return `<span class="srv-chip ${ad ? 'c-admin' : 'c-leader'}">${E(nm)} <small>(${ad ? 'admin' : 'leader'})</small><button type="button" data-k="u" data-rm="${id}" aria-label="Remove">&times;</button></span>`; }).join('') +
        [...Y].map(([id, nm]) => `<span class="srv-chip c-youth">${E(nm)} <small>(youth)</small><button type="button" data-k="y" data-rm="${id}" aria-label="Remove">&times;</button></span>`).join('');
    };
    const drawList = () => {
      const q = inp.value.trim().toLowerCase();
      const au = allU.filter(u => !q || u.full_name.toLowerCase().includes(q));
      const ay = allY.filter(y => !q || y.full_name.toLowerCase().includes(q));
      const ayShown = ay.slice(0, 80);
      const row = (k, id, nm, tag, cls) => `<label class="ldr-row ${cls}"><input type="checkbox" data-k="${k}" value="${id}"${(k === 'u' ? U : Y).has(String(id)) ? ' checked' : ''}/><span>${E(nm)}</span><small>${tag}</small></label>`;
      list.innerHTML =
        `<div class="ldr-h h-acc">Admin &amp; slot leaders (accounts) &middot; ${au.length}</div>` +
        (au.length ? au.map(u => u.role === 'secretariat' ? row('u', u.id, u.full_name, 'Admin', 'k-admin') : row('u', u.id, u.full_name, 'Leader', 'k-leader')).join('') : '<div class="srv-none">No account found</div>') +
        `<div class="ldr-h h-youth">Youth members &middot; ${ay.length}</div>` +
        (ayShown.length ? ayShown.map(y => row('y', y.id, y.full_name, 'Youth', 'k-youth')).join('') : '<div class="srv-none">No youth found</div>') +
        (ay.length > ayShown.length ? '<div class="srv-none">Type a name to see more...</div>' : '');
    };
    const load = async () => {
      [allU, allY] = [await accounts(), await youth()];
      drawList(); drawChips();
    };
    const toggle = () => { panel.classList.toggle('hidden', !on.checked); if (on.checked) load(); };

    on.addEventListener('change', toggle);
    inp.addEventListener('input', drawList);
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
    list.addEventListener('change', e => {
      const c = e.target.closest('input[type=checkbox]'); if (!c) return;
      const m = c.dataset.k === 'u' ? U : Y;
      const nm = c.closest('.ldr-row').querySelector('span').textContent;
      if (c.checked) m.set(String(c.value), nm); else m.delete(String(c.value));
      drawChips();
    });
    chips.addEventListener('click', e => {
      const b = e.target.closest('[data-rm]'); if (!b) return;
      (b.dataset.k === 'u' ? U : Y).delete(b.dataset.rm);
      drawChips(); drawList();
    });

    const api = {
      box,
      get() { return { leader_user_ids: on.checked ? [...U.keys()] : [], leader_youth_ids: on.checked ? [...Y.keys()] : [] }; },
      async set(userIds, youthIds) {
        U.clear(); Y.clear();
        [allU, allY] = [await accounts(), await youth()];
        (userIds || []).forEach(id => { const u = allU.find(x => String(x.id) === String(id)); if (u) U.set(String(u.id), u.full_name); });
        (youthIds || []).forEach(id => { const y = allY.find(x => String(x.id) === String(id)); if (y) Y.set(String(y.id), y.full_name); });
        on.checked = (U.size + Y.size) > 0;
        inp.value = '';
        panel.classList.toggle('hidden', !on.checked);
        drawList(); drawChips();
      },
      async loadFor(eventId) {
        api.reset();
        const d = await leadersOf(eventId);
        await api.set(d.user_ids, d.youth_ids);
      },
      reset() { on.checked = false; U.clear(); Y.clear(); inp.value = ''; panel.classList.add('hidden'); drawChips(); }
    };
    form.addEventListener('reset', () => setTimeout(api.reset, 0));
    drawChips();
    return api;
  }

  /* ---------- "Multiple slots?" = same event on several dates (calendar picker) ---------- */
  function mountDates(form, afterBox) {
    const dateInput = form.elements['event_date'];
    const dateField = dateInput.closest('.form-field');
    const box = document.createElement('div');
    box.className = 'mds-box';
    box.innerHTML = `
      <label class="srv-check"><input type="checkbox" class="mds-on"/> <span>This event has multiple slots (select several dates)</span></label>
      <div class="mds-panel hidden">
        <p class="srv-note">Tap every date for this event on the calendar (tap again to remove). One event is created for each date, with the same title, time, location and servers.</p>
        <div class="mcal">
          <div class="mcal-nav"><button type="button" class="mcal-prev" aria-label="Previous month">&lsaquo;</button><span class="mcal-title"></span><button type="button" class="mcal-next" aria-label="Next month">&rsaquo;</button></div>
          <div class="mcal-dow"><div>SU</div><div>MO</div><div>TU</div><div>WE</div><div>TH</div><div>FR</div><div>SA</div></div>
          <div class="mcal-grid"></div>
        </div>
        <div class="mcal-count"></div>
        <div class="srv-chips"></div>
      </div>`;
    afterBox.after(box);
    const on = box.querySelector('.mds-on'), panel = box.querySelector('.mds-panel'), chips = box.querySelector('.srv-chips'),
          grid = box.querySelector('.mcal-grid'), title = box.querySelector('.mcal-title'), count = box.querySelector('.mcal-count');
    const dates = new Set();
    const MN = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const ymd = (y, m, d) => y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    const now = new Date();
    let vy = now.getFullYear(), vm = now.getMonth();
    const todayStr = ymd(now.getFullYear(), now.getMonth(), now.getDate());

    const draw = () => {
      title.textContent = MN[vm] + ' ' + vy;
      const first = new Date(vy, vm, 1).getDay(), days = new Date(vy, vm + 1, 0).getDate();
      let h = '';
      for (let i = 0; i < first; i++) h += '<div class="mcal-blank"></div>';
      for (let d = 1; d <= days; d++) {
        const ds = ymd(vy, vm, d);
        h += `<button type="button" class="mcal-day${dates.has(ds) ? ' sel' : ''}${ds === todayStr ? ' today' : ''}" data-d="${ds}">${d}</button>`;
      }
      grid.innerHTML = h;
      const arr = [...dates].sort();
      count.textContent = arr.length ? arr.length + ' date(s) selected' : 'No dates selected yet.';
      chips.innerHTML = arr.map(d => `<span class="srv-chip mds-chip">${E(formatDate(d))}<button type="button" data-rm="${d}" aria-label="Remove">&times;</button></span>`).join('');
    };
    const toggle = () => {
      panel.classList.toggle('hidden', !on.checked);
      dateField.classList.toggle('hidden', on.checked);
      dateInput.required = !on.checked;
    };

    on.addEventListener('change', () => {
      if (on.checked && dateInput.value) {
        dates.add(dateInput.value);
        const p = dateInput.value.split('-'); vy = +p[0]; vm = +p[1] - 1;
      }
      toggle(); draw();
    });
    box.querySelector('.mcal-prev').addEventListener('click', () => { vm--; if (vm < 0) { vm = 11; vy--; } draw(); });
    box.querySelector('.mcal-next').addEventListener('click', () => { vm++; if (vm > 11) { vm = 0; vy++; } draw(); });
    grid.addEventListener('click', e => {
      const b = e.target.closest('.mcal-day'); if (!b) return;
      const d = b.dataset.d;
      if (dates.has(d)) dates.delete(d); else dates.add(d);
      draw();
    });
    chips.addEventListener('click', e => { const b = e.target.closest('[data-rm]'); if (b) { dates.delete(b.dataset.rm); draw(); } });

    const api = {
      get() { return { on: on.checked, dates: on.checked ? [...dates].sort() : [] }; },
      edit(date) { // editing an existing event: show the same calendar, current date already ticked
        dates.clear(); on.disabled = false; box.style.opacity = '';
        on.checked = true;
        if (date) { dates.add(date); const p = date.split('-'); vy = +p[0]; vm = +p[1] - 1; }
        toggle(); draw();
      },
      lock() { on.disabled = false; box.style.opacity = ''; },
      reset() { on.checked = false; on.disabled = false; box.style.opacity = ''; dates.clear(); toggle(); draw(); }
    };
    form.addEventListener('reset', () => setTimeout(api.reset, 0));
    draw();
    return api;
  }

  function pairRow(a, b) {
    const row = document.createElement('div');
    row.className = 'opt-row';
    a.before(row); row.appendChild(a); row.appendChild(b);
  }

  async function saveEvent(form, srv, mds, lead, base, after) {
    const md = mds.get();
    if (md.on && !md.dates.length) { alert('Add at least one date.'); return; }
    // Build the list of saves: { id, date }
    const jobs = [];
    if (!md.on) jobs.push({ id: base.id, date: base.event_date });
    else if (base.id) {
      // editing: keep the event on its date (or move it to the first picked date), extra dates become new events
      const keep = md.dates.includes(base.event_date) ? base.event_date : md.dates[0];
      jobs.push({ id: base.id, date: keep });
      md.dates.filter(d => d !== keep).forEach(d => jobs.push({ id: '', date: d }));
    } else md.dates.forEach(d => jobs.push({ id: '', date: d }));

    let ok = 0, last = '';
    for (const j of jobs) {
      const res = await apiCall('save_category_event', Object.assign({}, base, srv.get(), lead.get(), { id: j.id, event_date: j.date }));
      if (res.success) ok++; else { last = res.message; break; }
    }
    if (ok < jobs.length) { alert((ok ? ok + ' event(s) saved. ' : '') + last); if (ok) after(); return; }
    if (base.id) alert(jobs.length > 1 ? 'Event updated and ' + (jobs.length - 1) + ' more date(s) added.' : 'Event updated successfully.');
    else alert(md.on ? ok + ' events added.' : 'Event added successfully.');
    form.reset(); form.classList.add('hidden');
    after();
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const adm = $('adminEventForm'), cev = $('categoryEventForm');
    if (!adm || typeof apiCall !== 'function') return;
    const s = await apiCall('check_session');
    if (!(s.success && s.data.role === 'secretariat')) return;

    /* Type -> Category */
    const t = adm.elements['type'];
    if (t) {
      const fld = t.closest('.form-field');
      fld.innerHTML = '<label>Category</label><select name="category_id" required><option value="">— Select category —</option></select>';
    }
    const admSrv = mount(adm);
    const admLead = mountLeaders(adm, admSrv.box);
    const admMds = mountDates(adm, admLead.box);
    adm.__srv = admSrv; adm.__mds = admMds; adm.__lead = admLead;
    pairRow(admSrv.box, admLead.box);
    const catSrv = cev ? mount(cev) : null;
    const catLead = cev ? mountLeaders(cev, catSrv.box) : null;
    const catMds = cev ? mountDates(cev, catLead.box) : null;
    if (cev) pairRow(catSrv.box, catLead.box);
    refreshCats();

    $('adminShowEventFormBtn').addEventListener('click', () => { refreshCats(); admMds.reset(); admLead.reset(); });

    adm.addEventListener('submit', e => {
      e.preventDefault(); e.stopImmediatePropagation();
      const f = adm.elements;
      if (!f['category_id'].value) { alert('Please select a category.'); return; }
      saveEvent(adm, admSrv, admMds, admLead, {
        id: f['id'].value, category_id: f['category_id'].value, title: f['title'].value.trim(),
        event_date: f['event_date'].value, event_time: f['event_time'].value, location: f['location'].value.trim()
      }, () => loadEvents(true));
    }, true);

    if (cev) {
      cev.addEventListener('submit', e => {
        e.preventDefault(); e.stopImmediatePropagation();
        const f = cev.elements;
        saveEvent(cev, catSrv, catMds, catLead, {
          id: f['id'].value, category_id: f['category_id'].value, title: f['title'].value.trim(),
          event_date: f['event_date'].value, event_time: f['event_time'].value, location: f['location'].value.trim()
        }, () => loadAdminCategories());
      }, true);

      document.addEventListener('click', e => {
        const b = e.target.closest('.cat-ev-edit, .cat-add-event');
        if (!b) return;
        if (b.classList.contains('cat-add-event')) { catMds.reset(); catLead.reset(); return; }
        let found = null;
        ALL_CATEGORIES.forEach(c => c.events.forEach(ev => { if (String(ev.id) === b.dataset.id) found = ev; }));
        if (found) { catSrv.set(Number(found.has_servers) === 1, found.servers); catMds.edit(String(found.event_date).substring(0, 10)); catLead.loadFor(found.id); }
      });
    }
  });

  /* Editing from Admin Panel → Manage Events */
  const _editEvent = editEvent;
  editEvent = async function (id, isAdmin) {
    if (!isAdmin) return _editEvent(id, isAdmin);
    const ev = ALL_EVENTS.find(x => String(x.id) === String(id));
    const form = $('adminEventForm');
    if (!ev || !form || !form.elements['category_id']) return;
    await refreshCats();
    const f = form.elements;
    f['id'].value = ev.id; f['title'].value = ev.title;
    f['category_id'].value = ev.category_id || '';
    f['event_date'].value = ev.event_date; f['event_time'].value = ev.event_time ? ev.event_time.substring(0, 5) : '';
    f['location'].value = ev.location || '';
    if (form.__srv) form.__srv.set(Number(ev.has_servers) === 1, ev.servers);
    if (form.__mds) form.__mds.edit(String(ev.event_date).substring(0, 10));
    if (form.__lead) form.__lead.loadFor(ev.id);
    form.classList.remove('hidden'); form.scrollIntoView({ behavior: 'smooth' });
  };
})();

/* ---------- Real-time name search for attendance lists (all roles) ---------- */
(function () {
  const css = `
  .att-search{position:sticky;top:72px;z-index:20;background:var(--surface);padding:8px 0 10px;margin:0 0 4px}
  .att-search .box{position:relative;max-width:460px}
  .att-search .box i.fa-search{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px}
  .att-search input{width:100%;padding:11px 38px 11px 36px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;outline:none;background:var(--surface)}
  .att-search input:focus{border-color:var(--sapphire-glow);box-shadow:0 0 0 3px rgba(59,130,246,.10)}
  .att-search .clr{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:28px;height:28px;border:none;background:none;color:var(--text-soft);border-radius:50%;font-size:14px}
  .att-search .clr:hover{background:var(--bg)}
  .att-search .nm{font-size:12px;color:var(--text-faint);font-style:italic;margin-top:6px}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  function setup(tbodyId, closeId) {
    const tb = document.getElementById(tbodyId);
    if (!tb) return;
    const wrap = tb.closest('.table-wrap');
    if (!wrap) return;
    const box = document.createElement('div');
    box.className = 'att-search';
    box.innerHTML = '<div class="box"><i class="fas fa-search"></i><input type="text" placeholder="Search name..." autocomplete="off" aria-label="Search name"/><button type="button" class="clr hidden" aria-label="Clear"><i class="fas fa-times"></i></button></div><div class="nm hidden">No matching name.</div>';
    wrap.before(box);
    const inp = box.querySelector('input'), clr = box.querySelector('.clr'), nm = box.querySelector('.nm');

    const apply = () => {
      const q = inp.value.trim().toLowerCase();
      clr.classList.toggle('hidden', !q);
      let shown = 0, total = 0, grp = null, grpShown = 0;
      const closeGrp = () => { if (grp) grp.style.display = (!q || grpShown) ? '' : 'none'; };
      tb.querySelectorAll('tr').forEach(tr => {
        if (tr.classList.contains('att-group')) { closeGrp(); grp = tr; grpShown = 0; return; }
        if (!tr.dataset.memberId) return;
        total++;
        const ok = !q || String(tr.dataset.name || '').toLowerCase().includes(q);
        tr.style.display = ok ? '' : 'none';
        if (ok) { shown++; grpShown++; }
      });
      closeGrp();
      nm.classList.toggle('hidden', !(q && total && !shown));
    };
    inp.addEventListener('input', apply);
    inp.addEventListener('keydown', e => { if (e.key === 'Escape') { inp.value = ''; apply(); } });
    clr.addEventListener('click', () => { inp.value = ''; apply(); inp.focus(); });
    new MutationObserver(apply).observe(tb, { childList: true });
    const c = document.getElementById(closeId);
    if (c) c.addEventListener('click', () => { inp.value = ''; apply(); });
  }

  document.addEventListener('DOMContentLoaded', () => {
    setup('attendanceTableBody', 'closeAttPanel');
    setup('evAttTableBody', 'closeEvAttPanel');
  });

  // "Mark All" only touches the names currently shown by the search
  attMarkAll = function (tbody, s) {
    tbody.querySelectorAll('tr[data-member-id]').forEach(row => {
      if (row.dataset.status === 'excused' || row.style.display === 'none') return;
      attSetRow(row, s);
    });
  };
})();
