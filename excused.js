/* ============================================================
   PYM — Excused status with a REASON popup
   Load AFTER app.js, coy.js and records.js.
   Present / Absent: no reason.  Excused: popup asks for the reason.
   ============================================================ */

(function injectExcusedStyles() {
  const css = `
  .att-seg{display:inline-flex;gap:2px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:99px;padding:3px;white-space:nowrap}
  .att-seg button{border:none;background:none;font-size:11.5px;font-weight:700;padding:6px 12px;border-radius:99px;color:var(--text-soft);transition:all var(--dur) var(--ease)}
  .att-seg button:hover{color:var(--text-primary);background:var(--bg)}
  .att-seg button.active[data-s="present"]{background:var(--emerald);color:#fff;box-shadow:var(--shadow-green)}
  .att-seg button.active[data-s="absent"]{background:var(--crimson);color:#fff;box-shadow:0 4px 12px rgba(220,38,38,.28)}
  .att-seg button.active[data-s="excused"]{background:var(--amber);color:#fff;box-shadow:0 4px 12px rgba(217,119,6,.30)}
  .att-reason{width:100%;min-width:220px;border:1.5px solid #FCD34D;background:#FFFBEB;border-radius:var(--radius-sm);padding:8px 11px;font-size:12.5px;outline:none;cursor:pointer}
  .att-reason:hover{border-color:var(--amber)}
  .status-pill.excused{background:var(--amber-pale);color:var(--amber)}
  .status-dot.excused{background:var(--amber)}
  .att-group td{background:var(--ink);color:#fff!important;font-size:11px!important;font-weight:800!important;letter-spacing:.8px;text-transform:uppercase;padding:9px 14px!important}
  .att-quick-btn.excused-note{cursor:default;background:var(--amber-pale);color:var(--amber);border-color:#FCD34D}

  .rsn-back{position:fixed;inset:0;z-index:10000;background:rgba(8,16,31,.6);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:16px}
  .rsn-box{background:#fff;border-radius:18px;width:100%;max-width:420px;padding:22px;box-shadow:0 24px 70px rgba(0,0,0,.4);border-top:4px solid var(--amber)}
  .rsn-box h4{font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;margin-bottom:4px;color:var(--text-primary)}
  .rsn-box p{font-size:12.5px;color:var(--text-soft);margin-bottom:12px}
  .rsn-box p b{color:var(--text-primary)}
  .rsn-box textarea{width:100%;min-height:96px;resize:vertical;border:1.5px solid #FCD34D;background:#FFFBEB;border-radius:10px;padding:11px 13px;font-size:16px;font-family:inherit;outline:none;color:var(--text-primary)}
  .rsn-box textarea:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(217,119,6,.14)}
  .rsn-box textarea.invalid{border-color:var(--crimson);background:var(--crimson-pale)}
  .rsn-err{font-size:12px;color:var(--crimson);font-weight:600;min-height:16px;margin-top:6px}
  .rsn-actions{display:flex;gap:10px;margin-top:10px}
  .rsn-actions button{flex:1;min-height:46px;border-radius:10px;font-size:14px;font-weight:700;border:1.5px solid var(--border);background:#fff;color:var(--text-mid)}
  .rsn-actions button.ok{background:var(--amber);border-color:var(--amber);color:#fff;box-shadow:0 4px 12px rgba(217,119,6,.30)}
  `;
  const el = document.createElement('style');
  el.textContent = css;
  document.head.appendChild(el);
})();

async function attApi(action, payload = null) {
  try {
    const res = await fetch(`api_attendance.php?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    });
    return await res.json();
  } catch (err) {
    return { success: false, message: 'Network error. Please check your connection.' };
  }
}

/* ---------- Reason popup ---------- */
function attAskReason(name, initial) {
  return new Promise(resolve => {
    const back = document.createElement('div');
    back.className = 'rsn-back';
    back.innerHTML = `
      <div class="rsn-box" role="dialog" aria-modal="true" aria-label="Excused reason">
        <h4>Reason for Excused</h4>
        <p>Why is <b>${escapeHtml(name || 'this member')}</b> excused?</p>
        <textarea maxlength="255" placeholder="Type the reason..."></textarea>
        <div class="rsn-err"></div>
        <div class="rsn-actions">
          <button type="button" class="cancel">Cancel</button>
          <button type="button" class="ok">Save Reason</button>
        </div>
      </div>`;
    document.body.appendChild(back);
    const ta = back.querySelector('textarea');
    const err = back.querySelector('.rsn-err');
    ta.value = initial || '';
    setTimeout(() => ta.focus(), 50);

    const done = (v) => { document.removeEventListener('keydown', onKey, true); back.remove(); resolve(v); };
    const submit = () => {
      const v = ta.value.trim();
      if (!v) { ta.classList.add('invalid'); err.textContent = 'Please type the reason.'; ta.focus(); return; }
      done(v);
    };
    const onKey = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); done(null); }
      else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); submit(); }
    };
    document.addEventListener('keydown', onKey, true);
    ta.addEventListener('input', () => { ta.classList.remove('invalid'); err.textContent = ''; });
    back.querySelector('.cancel').addEventListener('click', () => done(null));
    back.querySelector('.ok').addEventListener('click', submit);
    back.addEventListener('mousedown', (e) => { if (e.target === back) done(null); });
  });
}

function attEnsureReasonHeader(tbody) {
  const tr = tbody.closest('table').querySelector('thead tr');
  if (tr && !tr.querySelector('.reason-th')) {
    const th = document.createElement('th');
    th.className = 'reason-th';
    th.textContent = 'Reason (if excused)';
    tr.appendChild(th);
  }
}

function attRowHtml(m) {
  const st = ['present', 'absent', 'excused'].includes(m.status) ? m.status : 'absent';
  const timeVal = m.time_in ? m.time_in.substring(0, 5) : '';
  const seg = ['present', 'absent', 'excused'].map(s =>
    `<button type="button" data-s="${s}" class="${s === st ? 'active' : ''}">${s.charAt(0).toUpperCase() + s.slice(1)}</button>`).join('');
  return `
    <tr data-member-id="${m.member_id}" data-status="${st}" data-name="${escapeHtml(m.full_name)}">
      <td>${escapeHtml(m.full_name)}</td>
      <td>${escapeHtml(m.slot_name || '—')}</td>
      <td><div class="att-seg">${seg}</div></td>
      <td><input type="time" value="${timeVal}" ${st === 'present' ? '' : 'disabled'}/></td>
      <td><input type="text" class="att-reason ${st === 'excused' ? '' : 'hidden'}" readonly maxlength="255" title="Tap to edit the reason" placeholder="Tap to add reason" value="${escapeHtml(m.reason || '')}"/></td>
    </tr>`;
}

async function attSetRow(row, s) {
  const ti = row.querySelector('input[type="time"]');
  const rs = row.querySelector('.att-reason');

  if (s === 'excused') {
    const reason = await attAskReason(row.dataset.name, rs.value);
    if (reason === null) return; // cancelled → keep previous status
    rs.value = reason;
    rs.classList.remove('hidden');
    ti.value = ''; ti.disabled = true;
  } else {
    rs.classList.add('hidden');
    rs.value = '';
    if (s === 'present') {
      ti.disabled = false;
      if (!ti.value) ti.value = new Date().toTimeString().substring(0, 5);
    } else {
      ti.value = ''; ti.disabled = true;
    }
  }
  row.dataset.status = s;
  row.querySelectorAll('.att-seg button').forEach(b => b.classList.toggle('active', b.dataset.s === s));
}

function attCollect(tbody) {
  const records = [];
  let bad = null;
  tbody.querySelectorAll('tr[data-member-id]').forEach(row => {
    const status = row.dataset.status || 'absent';
    const reason = row.querySelector('.att-reason').value.trim();
    if (status === 'excused' && !reason && !bad) bad = row;
    records.push({
      member_id: row.dataset.memberId, status,
      time_in: row.querySelector('input[type="time"]').value || null,
      reason: status === 'excused' ? reason : ''
    });
  });
  if (bad) {
    bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
    attAskReason(bad.dataset.name, '').then(r => {
      if (r) { const i = bad.querySelector('.att-reason'); i.value = r; i.classList.remove('hidden'); }
    });
    return null;
  }
  return records;
}

function attMarkAll(tbody, s) {
  tbody.querySelectorAll('tr[data-member-id]').forEach(row => {
    if (row.dataset.status === 'excused') return; // keep excused entries untouched
    attSetRow(row, s);
  });
}

/* ---------- Overrides of app.js functions ---------- */
async function loadAttendanceTable(slotId, date) {
  const tbody = document.getElementById('attendanceTableBody');
  attEnsureReasonHeader(tbody);
  tbody.innerHTML = '<tr><td colspan="5" class="empty-msg">Loading...</td></tr>';
  const res = await attApi('get_slot_attendance', { slot_id: slotId, date: date || CURRENT_ATT_DATE });
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
  if (!res.data.length) {
    tbody.innerHTML = CURRENT_USER && CURRENT_USER.role === 'leader' && !mySlotId
      ? '<tr><td colspan="5" class="empty-msg">You have no slot assigned. Ask Secretariat to assign you to a slot.</td></tr>'
      : '<tr><td colspan="5" class="empty-msg">No active youth members found in this slot.</td></tr>';
    return;
  }
  tbody.innerHTML = res.data.map(attRowHtml).join('');
}

async function openEventAttendance(eventId) {
  const info = CAT_EVENT_MAP[eventId];
  if (!info) return;
  CURRENT_EVENT_ID = eventId;
  const panel = document.getElementById('eventAttPanel');
  const tbody = document.getElementById('evAttTableBody');
  attEnsureReasonHeader(tbody);
  panel.classList.remove('hidden');
  document.getElementById('evAttTitle').textContent = info.ev.title;
  document.getElementById('evAttSub').textContent = info.cat + ' · ' + formatDate(info.ev.event_date) + (info.ev.event_time ? ' · ' + formatTime(info.ev.event_time) : '');
  tbody.innerHTML = '<tr><td colspan="5" class="empty-msg">Loading...</td></tr>';
  panel.scrollIntoView({ behavior: 'smooth' });

  const res = await attApi('get_event_att', { event_id: eventId });
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
  if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="5" class="empty-msg">No active youth members found.</td></tr>'; return; }
  tbody.innerHTML = attEventRowsHtml(res.data);
}

/* Servers first, attendees below (only when the event has servers) */
function attEventRowsHtml(data) {
  const sv = data.filter(r => Number(r.is_server) === 1), at = data.filter(r => Number(r.is_server) !== 1);
  if (!sv.length) return at.map(attRowHtml).join('');
  const h = t => `<tr class="att-group"><td colspan="5">${t}</td></tr>`;
  return h('Servers (' + sv.length + ')') + sv.map(attRowHtml).join('') + h('Attendees (' + at.length + ')') + at.map(attRowHtml).join('');
}

/* ---------- Wiring (replace old handlers with new ones) ---------- */
document.addEventListener('DOMContentLoaded', () => {
  const swap = (id, handler) => {
    const old = document.getElementById(id);
    if (!old) return;
    const fresh = old.cloneNode(true);
    old.replaceWith(fresh);
    fresh.addEventListener('click', handler);
  };

  ['attendanceTableBody', 'evAttTableBody'].forEach(id => {
    const tbody = document.getElementById(id);
    if (!tbody) return;
    attEnsureReasonHeader(tbody);
    tbody.addEventListener('click', async (e) => {
      const b = e.target.closest('.att-seg button');
      if (b) { await attSetRow(b.closest('tr'), b.dataset.s); return; }
      const r = e.target.closest('.att-reason');
      if (r && !r.classList.contains('hidden')) {
        const row = r.closest('tr');
        const v = await attAskReason(row.dataset.name, r.value);
        if (v !== null) r.value = v;
      }
    });
  });

  const slotBody = document.getElementById('attendanceTableBody');
  const evBody = document.getElementById('evAttTableBody');

  swap('markAllPresentBtn', () => attMarkAll(slotBody, 'present'));
  swap('markAllAbsentBtn', () => attMarkAll(slotBody, 'absent'));
  swap('evMarkAllPresentBtn', () => attMarkAll(evBody, 'present'));
  swap('evMarkAllAbsentBtn', () => attMarkAll(evBody, 'absent'));

  swap('saveAttendanceBtn', async () => {
    const slotId = document.getElementById('attendanceEventSelect').value;
    if (!slotId) { alert('Please select a service time first.'); return; }
    if (!CURRENT_ATT_DATE) { alert('No date selected.'); return; }
    const records = attCollect(slotBody);
    if (!records) return;
    const btn = document.getElementById('saveAttendanceBtn');
    btn.disabled = true;
    const res = await attApi('save_slot_attendance', { slot_id: slotId, date: CURRENT_ATT_DATE, records });
    btn.disabled = false;
    alert(res.message);
    if (res.success) { loadCalendar(); loadDashboardStats(); }
  });

  swap('saveEventAttendanceBtn', async () => {
    if (!CURRENT_EVENT_ID) { alert('No event selected.'); return; }
    const records = attCollect(evBody);
    if (!records) return;
    const btn = document.getElementById('saveEventAttendanceBtn');
    btn.disabled = true;
    const res = await attApi('save_event_att', { event_id: CURRENT_EVENT_ID, records });
    btn.disabled = false;
    alert(res.message);
    if (res.success) loadCalendar();
  });
});
