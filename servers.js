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
      lock(v) { // editing an existing event: single date only
        if (v) { on.checked = false; dates.clear(); toggle(); draw(); }
        on.disabled = !!v; box.style.opacity = v ? '.5' : '';
      },
      reset() { on.checked = false; on.disabled = false; box.style.opacity = ''; dates.clear(); toggle(); draw(); }
    };
    form.addEventListener('reset', () => setTimeout(api.reset, 0));
    draw();
    return api;
  }

  async function saveEvent(form, srv, mds, base, after) {
    const md = mds.get();
    if (md.on && !md.dates.length) { alert('Add at least one date.'); return; }
    const list = md.on ? md.dates : [base.event_date];
    let ok = 0, last = '';
    for (const d of list) {
      const res = await apiCall('save_category_event', Object.assign({}, base, srv.get(), { event_date: d }));
      if (res.success) ok++; else { last = res.message; break; }
    }
    if (ok < list.length) { alert((ok ? ok + ' event(s) saved. ' : '') + last); if (ok) after(); return; }
    alert(md.on ? ok + ' events added.' : (base.id ? 'Event updated successfully.' : 'Event added successfully.'));
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
    const admMds = mountDates(adm, admSrv.box);
    adm.__srv = admSrv; adm.__mds = admMds;
    const catSrv = cev ? mount(cev) : null;
    const catMds = cev ? mountDates(cev, catSrv.box) : null;
    refreshCats();

    $('adminShowEventFormBtn').addEventListener('click', () => { refreshCats(); admMds.lock(false); });

    adm.addEventListener('submit', e => {
      e.preventDefault(); e.stopImmediatePropagation();
      const f = adm.elements;
      if (!f['category_id'].value) { alert('Please select a category.'); return; }
      saveEvent(adm, admSrv, admMds, {
        id: f['id'].value, category_id: f['category_id'].value, title: f['title'].value.trim(),
        event_date: f['event_date'].value, event_time: f['event_time'].value, location: f['location'].value.trim()
      }, () => loadEvents(true));
    }, true);

    if (cev) {
      cev.addEventListener('submit', e => {
        e.preventDefault(); e.stopImmediatePropagation();
        const f = cev.elements;
        saveEvent(cev, catSrv, catMds, {
          id: f['id'].value, category_id: f['category_id'].value, title: f['title'].value.trim(),
          event_date: f['event_date'].value, event_time: f['event_time'].value, location: f['location'].value.trim()
        }, () => loadAdminCategories());
      }, true);

      document.addEventListener('click', e => {
        const b = e.target.closest('.cat-ev-edit, .cat-add-event');
        if (!b) return;
        if (b.classList.contains('cat-add-event')) { catMds.lock(false); return; }
        let found = null;
        ALL_CATEGORIES.forEach(c => c.events.forEach(ev => { if (String(ev.id) === b.dataset.id) found = ev; }));
        if (found) { catSrv.set(Number(found.has_servers) === 1, found.servers); catMds.lock(true); }
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
    if (form.__mds) form.__mds.lock(true);
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
