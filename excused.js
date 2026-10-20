/* ============================================================
   PYM — Excused status + reason for attendance marking
   Load AFTER app.js, coy.js and records.js.
   ============================================================ */

(function injectExcusedStyles() {
  const css = `
  .att-seg{display:inline-flex;gap:2px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:99px;padding:3px;white-space:nowrap}
  .att-seg button{border:none;background:none;font-size:11.5px;font-weight:700;padding:6px 12px;border-radius:99px;color:var(--text-soft);transition:all var(--dur) var(--ease)}
  .att-seg button:hover{color:var(--text-primary);background:var(--bg)}
  .att-seg button.active[data-s="present"]{background:var(--emerald);color:#fff;box-shadow:var(--shadow-green)}
  .att-seg button.active[data-s="absent"]{background:var(--crimson);color:#fff;box-shadow:0 4px 12px rgba(220,38,38,.28)}
  .att-seg button.active[data-s="excused"]{background:var(--amber);color:#fff;box-shadow:0 4px 12px rgba(217,119,6,.30)}
  .att-reason{width:100%;min-width:220px;border:1.5px solid #FCD34D;background:#FFFBEB;border-radius:var(--radius-sm);padding:8px 11px;font-size:12.5px;outline:none}
  .att-reason:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(217,119,6,.12)}
  .att-reason.invalid{border-color:var(--crimson);background:var(--crimson-pale)}
  .status-pill.excused{background:var(--amber-pale);color:var(--amber)}
  .status-dot.excused{background:var(--amber)}
  .att-quick-btn.excused-note{cursor:default;background:var(--amber-pale);color:var(--amber);border-color:#FCD34D}
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
    <tr data-member-id="${m.member_id}" data-status="${st}">
      <td>${escapeHtml(m.full_name)}</td>
      <td>${escapeHtml(m.slot_name || '—')}</td>
      <td><div class="att-seg">${seg}</div></td>
      <td><input type="time" value="${timeVal}" ${st === 'present' ? '' : 'disabled'}/></td>
      <td><input type="text" class="att-reason ${st === 'excused' ? '' : 'hidden'}" maxlength="255" placeholder="Type the reason (required)" value="${escapeHtml(m.reason || '')}"/></td>
    </tr>`;
}

function attSetRow(row, s) {
  row.dataset.status = s;
  row.querySelectorAll('.att-seg button').forEach(b => b.classList.toggle('active', b.dataset.s === s));
  const ti = row.querySelector('input[type="time"]');
  const rs = row.querySelector('.att-reason');
  if (s === 'present') {
    ti.disabled = false;
    if (!ti.value) ti.value = new Date().toTimeString().substring(0, 5);
  } else {
    ti.value = ''; ti.disabled = true;
  }
  if (s === 'excused') { rs.classList.remove('hidden'); rs.focus(); }
  else { rs.classList.add('hidden'); rs.value = ''; rs.classList.remove('invalid'); }
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
    const inp = bad.querySelector('.att-reason');
    inp.classList.add('invalid');
    inp.scrollIntoView({ behavior: 'smooth', block: 'center' });
    inp.focus();
    alert('Please type the reason for every member marked Excused.');
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
  tbody.innerHTML = res.data.map(attRowHtml).join('');
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
    tbody.addEventListener('click', (e) => {
      const b = e.target.closest('.att-seg button');
      if (b) attSetRow(b.closest('tr'), b.dataset.s);
    });
    tbody.addEventListener('input', (e) => {
      if (e.target.classList.contains('att-reason')) e.target.classList.remove('invalid');
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
  });
});
