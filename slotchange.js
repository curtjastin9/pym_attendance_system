/* PYM — Slot change requests (icon on Youth Members + confirm/decline card). Loaded last on index.php. */
(function () {
  const css = `
  .sc-btn:hover{background:var(--amber)!important;color:#fff!important;border-color:var(--amber)!important}
  .sc-card{margin-bottom:18px;border-top:3px solid var(--amber)}
  .sc-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);flex-wrap:wrap}
  .sc-row:last-child{border-bottom:none}
  .sc-txt{flex:1;min-width:220px;font-size:13px;line-height:1.5}
  .sc-txt small{display:block;color:var(--text-soft);font-size:11.5px}
  .sc-act{display:flex;gap:8px}
  .sc-act button{border:none;border-radius:8px;padding:9px 14px;font-size:12.5px;font-weight:700;min-height:38px}
  .sc-ok{background:var(--emerald);color:#fff}.sc-no{background:var(--crimson-pale);color:var(--crimson)}.sc-cn{background:var(--surface-2);color:var(--text-mid);border:1.5px solid var(--border)!important}
  .sc-h{font-size:12px;color:var(--text-soft);padding:6px 0;border-bottom:1px dashed var(--border)}
  .sc-h b.approved{color:var(--emerald)}.sc-h b.rejected,.sc-h b.cancelled{color:var(--crimson)}
  .sc-back{position:fixed;inset:0;z-index:10000;background:rgba(8,16,31,.6);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:16px}
  .sc-box{background:#fff;border-radius:18px;width:100%;max-width:440px;padding:22px;box-shadow:0 24px 70px rgba(0,0,0,.4);border-top:4px solid var(--amber);max-height:92vh;overflow:auto}
  .sc-box h4{font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;margin-bottom:4px}
  .sc-box p{font-size:12.5px;color:var(--text-soft);margin-bottom:10px}
  .sc-box p b{color:var(--text-primary)}
  .sc-box label{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--text-soft);margin:12px 0 5px}
  .sc-box select{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:11px 13px;font-size:16px;outline:none;background-color:#fff}
  .sc-box .sc-n{font-size:11.5px;color:var(--text-soft);margin-top:10px;line-height:1.5}
  .sc-box .sc-b{display:flex;gap:10px;margin-top:14px}
  .sc-box .sc-b button{flex:1;min-height:46px;border-radius:10px;font-size:14px;font-weight:700;border:1.5px solid var(--border);background:#fff;color:var(--text-mid)}
  .sc-box .sc-b button.ok{background:var(--amber);border-color:var(--amber);color:#fff}
  .sc-box .sc-b button:disabled{opacity:.6}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  const $ = id => document.getElementById(id);
  const sc = async (a, p) => {
    try {
      const r = await fetch('api_slotchange.php?action=' + a, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(p || {}) });
      return await r.json();
    } catch (e) { return { success: false, message: 'Network error. Please check your connection.' }; }
  };
  const E = s => escapeHtml(s);
  const when = s => { if (!s) return ''; const [d, t] = String(s).split(' '); return formatDate(d) + (t ? ' · ' + formatTime(t.substring(0, 5)) : ''); };

  /* ---------- Request modal ---------- */
  async function openModal(memberId) {
    const res = await sc('options');
    if (!res.success) { alert(res.message); return; }
    const { slots, members } = res.data;
    const m = members.find(x => String(x.id) === String(memberId));
    if (!m) { alert('Only active members that already have a slot can change slot.'); return; }
    const cur = slots.find(s => String(s.id) === String(m.slot_id));
    const targets = slots.filter(s => String(s.id) !== String(m.slot_id));
    const back = document.createElement('div');
    back.className = 'sc-back';
    back.innerHTML = `<div class="sc-box" role="dialog" aria-modal="true">
      <h4>Request Slot Change</h4>
      <p><b>${E(m.full_name)}</b> is now in <b>${E(cur ? cur.name : '—')}</b>.</p>
      <label>Move to</label>
      <select id="scTo"><option value="">— Select new slot —</option>${targets.map(s => `<option value="${s.id}">${E(s.name)}${s.leaders.length ? ' · Leader: ' + E(s.leaders.join(', ')) : ''}</option>`).join('')}</select>
      <label>Swap with (optional)</label>
      <select id="scSwap"><option value="">No swap — just move</option></select>
      <div class="sc-n">The request goes to the leader of the new slot. The change only happens after they confirm. Past attendance stays in the old slot.</div>
      <div class="sc-b"><button type="button" class="cancel">Cancel</button><button type="button" class="ok">Send Request</button></div></div>`;
    document.body.appendChild(back);
    const to = back.querySelector('#scTo'), sw = back.querySelector('#scSwap'), ok = back.querySelector('.ok');
    to.addEventListener('change', () => {
      sw.innerHTML = '<option value="">No swap — just move</option>' +
        members.filter(x => String(x.slot_id) === to.value).map(x => `<option value="${x.id}">${E(x.full_name)}</option>`).join('');
    });
    const close = () => { document.removeEventListener('keydown', onKey, true); back.remove(); };
    const onKey = e => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', onKey, true);
    back.querySelector('.cancel').addEventListener('click', close);
    back.addEventListener('mousedown', e => { if (e.target === back) close(); });
    ok.addEventListener('click', async () => {
      if (!to.value) { alert('Please choose the new slot.'); return; }
      ok.disabled = true;
      const r = await sc('create', { member_id: m.id, to_slot: to.value, swap_member_id: sw.value || null });
      ok.disabled = false;
      alert(r.message);
      if (r.success) { close(); refresh(); }
    });
  }

  /* ---------- Icon on each Youth Members row ---------- */
  function addIcons() {
    document.querySelectorAll('#youthTableBody tr').forEach(tr => {
      const d = tr.querySelector('.delete-btn, .edit-btn');
      if (!d || tr.querySelector('.sc-btn')) return;
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'table-btn sc-btn'; b.dataset.id = d.dataset.id;
      b.title = 'Request slot change'; b.setAttribute('aria-label', 'Request slot change');
      b.innerHTML = '<i class="fas fa-right-left"></i>';
      d.parentElement.appendChild(b);
    });
  }

  /* ---------- Requests card ---------- */
  function desc(r) {
    let t = `<b>${E(r.member_name)}</b> · ${E(r.from_name)} → <b>${E(r.to_name)}</b>`;
    if (r.swap_name) t += `<br>Swap with <b>${E(r.swap_name)}</b> (${E(r.to_name)} → ${E(r.from_name)})`;
    return t;
  }
  function html(d) {
    if (!d.pending.length && !d.history.length) return '';
    const p = d.pending.map(r => `<div class="sc-row"><div class="sc-txt">${desc(r)}<small>Requested by ${E(r.requested_name || '—')} · ${when(r.created_at)}${r.can_decide ? '' : ' · waiting for the leader of ' + E(r.to_name)}</small></div>
      <div class="sc-act">${r.can_decide ? `<button class="sc-ok" data-sc="approve" data-id="${r.id}"><i class="fas fa-check"></i> Confirm</button><button class="sc-no" data-sc="reject" data-id="${r.id}"><i class="fas fa-times"></i> Decline</button>` : ''}${r.can_cancel ? `<button class="sc-cn" data-sc="cancel" data-id="${r.id}">Cancel</button>` : ''}</div></div>`).join('');
    const h = d.history.map(r => `<div class="sc-h"><b class="${E(r.status)}">${E(r.status)}</b> · ${E(r.member_name)}: ${E(r.from_name)} → ${E(r.to_name)}${r.swap_name ? ' (swap ' + E(r.swap_name) + ')' : ''}<br><small>${when(r.decided_at || r.created_at)}${r.decided_by ? ' · ' + E(r.decided_by) : ''}</small></div>`).join('');
    return `<div class="card-header"><div><h3><i class="fas fa-right-left" style="color:var(--amber);margin-right:8px"></i>Slot change requests${d.pending.length ? ` <span class="cat-badge">${d.pending.length} pending</span>` : ''}</h3>
      <p class="card-sub">The leader of the new slot confirms. Past attendance always stays in the old slot.</p></div></div>
      ${p || '<p class="empty-msg" style="padding:10px">No pending requests.</p>'}${h ? '<div style="margin-top:10px">' + h + '</div>' : ''}`;
  }
  function box(id, parentSel, before) {
    let b = $(id);
    if (b) return b;
    const par = document.querySelector(parentSel);
    if (!par) return null;
    b = document.createElement('div'); b.id = id; b.className = 'card sc-card hidden';
    const ref = before ? par.querySelector(before) : par.firstChild;
    par.insertBefore(b, ref || par.firstChild);
    return b;
  }
  async function refresh() {
    const res = await sc('list');
    const boxes = [box('scDash', '#page-dashboard', '.stats-grid'), box('scYouth', '#page-youth')].filter(Boolean);
    if (!res.success) { boxes.forEach(b => b.classList.add('hidden')); return; }
    const h = html(res.data);
    boxes.forEach(b => { b.innerHTML = h; b.classList.toggle('hidden', !h); });
  }

  document.addEventListener('click', async e => {
    const ic = e.target.closest('#youthTableBody .sc-btn');
    if (ic) { openModal(ic.dataset.id); return; }
    const b = e.target.closest('[data-sc]');
    if (!b) return;
    if (b.dataset.sc === 'reject' && !confirm('Decline this slot change request?')) return;
    if (b.dataset.sc === 'approve' && !confirm('Confirm this slot change? The member will move to your slot from today.')) return;
    b.disabled = true;
    const r = await sc(b.dataset.sc, { id: b.dataset.id });
    alert(r.message);
    try { await loadYouth(); } catch (_) {}
    try { await loadDashboardStats(); } catch (_) {}
    refresh();
  });

  const _ly = loadYouth;
  loadYouth = async function () { await _ly(); addIcons(); refresh(); };
  const _lds = loadDashboardStats;
  loadDashboardStats = async function () { await _lds(); refresh(); };

  document.addEventListener('DOMContentLoaded', () => {
    const tb = $('youthTableBody');
    if (tb) new MutationObserver(addIcons).observe(tb, { childList: true });
    refresh();
    setInterval(() => { if (!document.hidden) refresh(); }, 60000);
  });
})();

/* ---------- Time Slot box in the Youth Members edit/add form ---------- */
(function () {
  const $ = id => document.getElementById(id);
  const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  let SLOTS = null;

  async function loadSlotsList() {
    if (SLOTS) return SLOTS;
    const r = await apiCall('get_slots');
    SLOTS = r.success ? r.data : [];
    return SLOTS;
  }
  async function fillSelect(sel) {
    const cur = sel.value;
    const slots = await loadSlotsList();
    sel.innerHTML = '<option value="">— None —</option>' + slots.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
    sel.value = cur;
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const form = $('youthForm');
    if (!form || typeof apiCall !== 'function') return;
    const s = await apiCall('check_session');
    if (!(s.success && s.data.role === 'secretariat')) return;

    const row = document.createElement('div');
    row.className = 'form-row';
    row.innerHTML = '<div class="form-field"><label>Time Slot</label><select name="slot_id" id="youthSlotSel"><option value="">— None —</option></select></div>';
    const hint = form.querySelector('p');
    if (hint) hint.remove();
    form.querySelector('.form-actions').before(row);
    const sel = $('youthSlotSel');
    await fillSelect(sel);

    $('showYouthFormBtn').addEventListener('click', () => { sel.value = ''; fillSelect(sel); });

    form.addEventListener('submit', async e => {
      e.preventDefault(); e.stopImmediatePropagation();
      let data = {
        id: form.id.value, full_name: form.full_name.value.trim(), status: form.status.value,
        scholar_type: form.elements['scholar_type'].value, slot_id: sel.value
      };
      if (form.id.value && form.dataset.member) data = Object.assign(JSON.parse(form.dataset.member), data);
      const res = await apiCall('save_youth', data);
      alert(res.message);
      if (res.success) { form.reset(); form.dataset.member = ''; form.classList.add('hidden'); SLOTS = null; loadYouth(); }
    }, true);

    const _ey = editYouth;
    editYouth = async function (id, list) {
      _ey(id, list);
      const m = list.find(x => String(x.id) === String(id));
      await fillSelect(sel);
      sel.value = m && m.slot_id ? String(m.slot_id) : '';
    };
  });
})();

/* ---------- Events: year bar + date status highlight ---------- */
(function () {
  const st = document.createElement('style');
  st.textContent = `.ev-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin:0 0 14px}
  .ev-years,.ev-leg{display:flex;gap:6px;flex-wrap:wrap}
  .ev-yr{border:1.5px solid #CBD5E1;background:#fff;border-radius:99px;padding:6px 16px;font-size:12.5px;font-weight:800;cursor:pointer;color:#475569}
  .ev-yr.on{background:#1D4ED8;border-color:#1D4ED8;color:#fff}
  .ev-yr small{opacity:.7;margin-left:4px;font-weight:700}
  .ev-chip{font-size:11.5px;font-weight:800;border-radius:99px;padding:4px 11px}
  .ev-chip.done,.ev-st-done{background:#D1FAE5;color:#047857}
  .ev-chip.close,.ev-st-close{background:#FEF3C7;color:#B45309}
  .ev-chip.far,.ev-st-far{background:#EDE9FE;color:#6D28D9}
  .ev-st{display:inline-block;font-size:10.5px;font-weight:800;border-radius:99px;padding:2px 8px;margin-top:3px}
  tr.ev-done td{background:#F0FDF4}tr.ev-done td:first-child{box-shadow:inset 5px 0 0 #10B981}
  tr.ev-close td{background:#FFFBEB}tr.ev-close td:first-child{box-shadow:inset 5px 0 0 #F59E0B}
  tr.ev-far td{background:#F5F3FF}tr.ev-far td:first-child{box-shadow:inset 5px 0 0 #7C3AED}`;
  document.head.appendChild(st);
  const SEL = {}, DAY = 864e5;
  function info(d) {
    const t = new Date(); t.setHours(0, 0, 0, 0);
    const n = Math.round((new Date(d + 'T00:00:00') - t) / DAY);
    if (n < 0) return { k: 'done', t: 'Done' };
    if (n === 0) return { k: 'close', t: 'Today' };
    if (n <= 30) return { k: 'close', t: n === 1 ? 'Tomorrow' : 'In ' + n + ' days' };
    return { k: 'far', t: n < 60 ? 'In ' + n + ' days' : 'In ' + Math.round(n / 30) + ' months' };
  }
  function paint(id) {
    const tb = document.getElementById(id);
    if (!tb || typeof ALL_EVENTS === 'undefined') return;
    const rows = Array.from(tb.querySelectorAll('tr'));
    if (!ALL_EVENTS.length || rows.length !== ALL_EVENTS.length) return;
    const cy = String(new Date().getFullYear());
    const ys = new Set([cy]);
    rows.forEach((r, i) => {
      const d = String(ALL_EVENTS[i].event_date || '').substring(0, 10), s = info(d);
      r.dataset.y = d.substring(0, 4); ys.add(d.substring(0, 4));
      r.classList.remove('ev-done', 'ev-close', 'ev-far'); r.classList.add('ev-' + s.k); r.dataset.k = s.k;
      const td = r.cells[1]; td.querySelectorAll('.ev-st').forEach(x => x.remove());
      td.insertAdjacentHTML('beforeend', '<br><span class="ev-st ev-st-' + s.k + '">' + s.t + '</span>');
    });
    if (!SEL[id]) SEL[id] = cy;
    let bar = tb._evBar;
    if (!bar) {
      bar = document.createElement('div'); bar.className = 'ev-bar'; tb._evBar = bar;
      const w = tb.closest('.table-wrap') || tb.closest('table');
      w.parentNode.insertBefore(bar, w);
      bar.addEventListener('click', e => { const b = e.target.closest('.ev-yr'); if (b) { SEL[id] = b.dataset.y; apply(); } });
    }
    function apply() {
      const sel = SEL[id], c = { done: 0, close: 0, far: 0 };
      rows.forEach(r => { const ok = sel === 'all' || r.dataset.y === sel; r.style.display = ok ? '' : 'none'; if (ok) c[r.dataset.k]++; });
      const yl = Array.from(ys).sort();
      bar.innerHTML = '<div class="ev-years">' + ['all'].concat(yl).map(y => '<button type="button" class="ev-yr' + (y === sel ? ' on' : '') + '" data-y="' + y + '">' + (y === 'all' ? 'All' : y) +
        '<small>' + rows.filter(r => y === 'all' || r.dataset.y === y).length + '</small></button>').join('') + '</div>' +
        '<div class="ev-leg"><span class="ev-chip done">Done ' + c.done + '</span><span class="ev-chip close">Close (≤30 days) ' + c.close + '</span><span class="ev-chip far">Far ' + c.far + '</span></div>';
    }
    apply();
  }
  const _le = loadEvents;
  loadEvents = async function (a) { await _le(a); try { paint(a ? 'adminEventsTableBody' : 'eventsTableBody'); } catch (e) {} };
})();
