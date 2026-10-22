/* PYM extras — not-submitted alerts, follow-up list, self-service account, visitors + Volunteer. Load LAST on index.php. */
(function () {
  const css = `
  .x-warn{background:#FFFBEB;border:1.5px solid #FCD34D;border-left:5px solid var(--amber);border-radius:var(--radius);padding:14px 18px;margin-bottom:18px}
  .x-warn-h{font-size:13.5px;font-weight:800;color:#92400E;display:flex;align-items:center;gap:8px;margin-bottom:8px}
  .x-warn-h b{background:var(--amber);color:#fff;border-radius:99px;padding:1px 9px;font-size:11.5px}
  .x-warn-b{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px}
  .x-chip{background:#fff;border:1px solid #FCD34D;color:#92400E;font-size:12px;font-weight:700;padding:4px 11px;border-radius:99px}
  .x-warn small{color:#B45309;font-size:11.5px}
  .x-fu-row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border);flex-wrap:wrap}
  .x-fu-row:last-child{border-bottom:none}
  .x-fu-name{flex:1;min-width:160px;font-size:13.5px;font-weight:700}
  .x-fu-name small{display:block;font-weight:500;color:var(--text-soft);font-size:11.5px}
  .x-fu-pill{background:var(--crimson-pale);color:var(--crimson);font-size:11.5px;font-weight:800;padding:4px 11px;border-radius:99px}
  .x-fu-last{font-size:11.5px;color:var(--text-soft);min-width:130px;text-align:right}
  .att-seg button.active[data-s="volunteer"]{background:var(--violet-mid);color:#fff;box-shadow:0 4px 12px rgba(124,58,237,.28)}
  .status-pill.volunteer{background:var(--violet-pale);color:var(--violet)}
  .status-dot.volunteer{background:var(--violet-mid)}
  .att-tag{font-size:10.5px;font-weight:700;border-radius:99px;padding:2px 8px;display:inline-block;margin-top:3px}
  .att-tag.vis{background:var(--violet-pale);color:var(--violet)}
  .att-tag.else{background:var(--amber-pale);color:var(--amber)}
  .vis-box{margin:0 0 14px}
  .vis-search{position:relative;max-width:420px}
  .vis-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px}
  .vis-search input{width:100%;padding:10px 12px 10px 34px;border:1.5px dashed var(--border-mid);border-radius:var(--radius-sm);font-size:13px;outline:none}
  .vis-search input:focus{border-color:var(--sapphire-glow);border-style:solid}
  .vis-list{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:30;max-height:230px;overflow-y:auto;background:var(--surface);border:1.5px solid var(--border-mid);border-radius:8px;box-shadow:var(--shadow-md);padding:4px}
  .vis-opt{padding:8px 10px;font-size:13px;border-radius:6px;cursor:pointer}
  .vis-opt:hover{background:var(--sapphire-pale)}
  .vis-opt small{color:var(--text-soft);margin-left:6px}
  .x-acc .form-field{max-width:360px}
  .x-mail-cur{font-size:13px;color:var(--text-mid);margin-bottom:12px}
  @media(max-width:640px){.att-seg{grid-template-columns:repeat(2,1fr)!important}.x-fu-last{text-align:left}}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  const $ = id => document.getElementById(id);
  const acc = async (a, p) => {
    try {
      const r = await fetch('api_account.php?action=' + a, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(p || {}) });
      return await r.json();
    } catch (e) { return { success: false, message: 'Network error. Please check your connection.' }; }
  };

  /* ---------- Dashboard: not-submitted alerts + follow-up list ---------- */
  async function xAlerts() {
    const page = $('page-dashboard');
    if (!page) return;
    let box = $('xAlerts'), fu = $('xFollow');
    if (!box) { box = document.createElement('div'); box.id = 'xAlerts'; page.insertBefore(box, page.querySelector('.stats-grid')); }
    if (!fu) { fu = document.createElement('div'); fu.id = 'xFollow'; fu.className = 'card'; fu.style.marginTop = '18px'; page.appendChild(fu); }
    const res = await apiCall('get_alerts');
    if (!res.success) { box.innerHTML = ''; fu.classList.add('hidden'); return; }
    const ns = res.data.not_submitted || [], f = res.data.followup || [];
    box.innerHTML = ns.length ? `<div class="x-warn"><div class="x-warn-h"><i class="fas fa-triangle-exclamation"></i> Attendance not submitted <b>${ns.length}</b></div>
      <div class="x-warn-b">${ns.map(n => `<span class="x-chip">${escapeHtml(n.name)} · ${formatDate(n.date)}</span>`).join('')}</div>
      <small>Open Attendance and press Save for each session, even if everyone was absent.</small></div>` : '';
    fu.classList.remove('hidden');
    fu.innerHTML = `<div class="card-header"><div><h3><i class="fas fa-phone-volume" style="color:var(--amber);margin-right:8px"></i>Follow-up needed</h3>
      <p class="card-sub">Members who missed 3 or more services in a row</p></div></div>` +
      (f.length ? f.map(m => `<div class="x-fu-row"><div class="x-fu-name">${escapeHtml(m.full_name)}<small>${escapeHtml(m.slot_name || '')}</small></div>
        <span class="x-fu-pill">${m.streak} in a row${m.excused ? ' · ' + m.excused + ' excused' : ''}</span>
        <span class="x-fu-last">${m.last_attended ? 'Last attended ' + formatDate(m.last_attended) : 'No attendance yet'}</span></div>`).join('')
        : '<p class="empty-msg">No one needs a follow-up right now.</p>');
  }
  const _lds = loadDashboardStats;
  loadDashboardStats = async function () { await _lds(); try { await xAlerts(); } catch (e) {} };

  /* ---------- Attendance rows: Volunteer + visitors ---------- */
  const NOW = () => new Date().toTimeString().substring(0, 5);
  window.attRowHtml = function (m) {
    const ST = ['present', 'absent', 'excused', 'volunteer'];
    const s = ST.includes(m.status) ? m.status : 'absent';
    const vis = Number(m.visitor) === 1 || m.visitor === true;
    const tv = m.time_in ? m.time_in.substring(0, 5) : '';
    const seg = ST.map(x => `<button type="button" data-s="${x}" class="${x === s ? 'active' : ''}">${x.charAt(0).toUpperCase() + x.slice(1)}</button>`).join('');
    const tag = vis ? `<br><span class="att-tag vis">Visitor · from ${escapeHtml(m.slot_name || 'no slot')}</span>`
      : (m.elsewhere ? `<br><span class="att-tag else">Attended ${escapeHtml(m.elsewhere)}</span>` : '');
    return `<tr data-member-id="${m.member_id}" data-status="${s}" data-name="${escapeHtml(m.full_name)}" data-visitor="${vis ? 1 : 0}">
      <td>${escapeHtml(m.full_name)}${tag}</td>
      <td>${escapeHtml(m.slot_name || '—')}</td>
      <td><div class="att-seg">${seg}</div></td>
      <td><input type="time" value="${tv}" ${s === 'present' || s === 'volunteer' ? '' : 'disabled'}/></td>
      <td><input type="text" class="att-reason ${s === 'excused' ? '' : 'hidden'}" readonly maxlength="255" title="Tap to edit the reason" placeholder="Tap to add reason" value="${escapeHtml(m.reason || '')}"/></td>
    </tr>`;
  };
  attRowHtml = window.attRowHtml;

  attSetRow = async function (row, s) {
    const ti = row.querySelector('input[type="time"]'), rs = row.querySelector('.att-reason');
    if (s === 'excused') {
      const reason = await attAskReason(row.dataset.name, rs.value);
      if (reason === null) return;
      rs.value = reason; rs.classList.remove('hidden');
      ti.value = ''; ti.disabled = true;
    } else {
      rs.classList.add('hidden'); rs.value = '';
      if (s === 'present') { ti.disabled = false; if (!ti.value) ti.value = NOW(); }
      else if (s === 'volunteer') { ti.disabled = false; }
      else { ti.value = ''; ti.disabled = true; }
    }
    row.dataset.status = s;
    row.querySelectorAll('.att-seg button').forEach(b => b.classList.toggle('active', b.dataset.s === s));
  };

  attCollect = function (tbody) {
    const records = []; let bad = null;
    tbody.querySelectorAll('tr[data-member-id]').forEach(row => {
      const status = row.dataset.status || 'absent';
      const reason = row.querySelector('.att-reason').value.trim();
      if (status === 'excused' && !reason && !bad) bad = row;
      records.push({
        member_id: row.dataset.memberId, status,
        time_in: row.querySelector('input[type="time"]').value || null,
        reason: status === 'excused' ? reason : '',
        visitor: row.dataset.visitor === '1'
      });
    });
    if (bad) {
      bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
      attAskReason(bad.dataset.name, '').then(r => { if (r) { const i = bad.querySelector('.att-reason'); i.value = r; i.classList.remove('hidden'); } });
      return null;
    }
    return records;
  };

  attMarkAll = function (tbody, s) {
    tbody.querySelectorAll('tr[data-member-id]').forEach(row => {
      if (row.dataset.status === 'excused' || row.dataset.status === 'volunteer') return;
      attSetRow(row, s);
    });
  };

  function addVisitor(m) {
    const tb = $('attendanceTableBody');
    if (tb.querySelector(`tr[data-member-id="${m.id}"]`)) { alert('That member is already in the list.'); return; }
    const em = tb.querySelector('td.empty-msg'); if (em) em.closest('tr').remove();
    tb.insertAdjacentHTML('beforeend', attRowHtml({ member_id: m.id, full_name: m.full_name, slot_name: m.slot_name, status: 'present', time_in: NOW(), visitor: 1 }));
  }

  /* ---------- Wiring ---------- */
  document.addEventListener('DOMContentLoaded', () => {
    // Visitor search
    const qa = document.querySelector('#attendancePanel .att-quick-actions');
    if (qa) {
      const box = document.createElement('div');
      box.className = 'vis-box';
      box.innerHTML = '<div class="vis-search"><i class="fas fa-user-plus"></i><input type="text" id="visInput" placeholder="Add a visitor / transferred member (search name)..." autocomplete="off"/><div id="visList" class="vis-list hidden"></div></div>';
      qa.after(box);
      let t = null;
      $('visInput').addEventListener('input', e => {
        clearTimeout(t);
        const q = e.target.value.trim(), list = $('visList');
        if (q.length < 2) { list.classList.add('hidden'); return; }
        t = setTimeout(async () => {
          const sid = $('attendanceEventSelect').value;
          if (!sid) { alert('Please select a service time first.'); return; }
          const r = await attApi('search_members', { slot_id: sid, q });
          list.innerHTML = r.success && r.data.length
            ? r.data.map(m => `<div class="vis-opt" data-id="${m.id}" data-name="${escapeHtml(m.full_name)}" data-slot="${escapeHtml(m.slot_name || '')}">${escapeHtml(m.full_name)}<small>${escapeHtml(m.slot_name || 'No slot')}</small></div>`).join('')
            : '<div class="vis-opt" style="color:var(--text-faint);cursor:default">No match</div>';
          list.classList.remove('hidden');
        }, 300);
      });
      document.addEventListener('mousedown', e => {
        const o = e.target.closest('.vis-opt[data-id]');
        if (o) { e.preventDefault(); addVisitor({ id: o.dataset.id, full_name: o.dataset.name, slot_name: o.dataset.slot }); $('visInput').value = ''; $('visList').classList.add('hidden'); }
        else if (!e.target.closest('.vis-search')) $('visList').classList.add('hidden');
      });
    }

    // Settings: change password + email
    const ps = $('page-settings');
    if (ps) {
      ps.insertAdjacentHTML('beforeend', `
      <div class="card x-acc" style="margin-top:20px">
        <div class="card-header"><div><h3>Change Password</h3><p class="card-sub">At least 6 characters</p></div></div>
        <form id="xPwForm" class="data-form">
          <div class="form-row"><div class="form-field"><label>Current password</label><input type="password" name="current" required autocomplete="current-password"/></div></div>
          <div class="form-row"><div class="form-field"><label>New password</label><input type="password" name="password" required autocomplete="new-password"/></div>
          <div class="form-field"><label>Confirm new password</label><input type="password" name="confirm" required autocomplete="new-password"/></div></div>
          <div class="form-actions"><button type="submit" class="auth-btn small"><i class="fas fa-key"></i> Update Password</button></div>
        </form>
      </div>
      <div class="card x-acc" style="margin-top:20px">
        <div class="card-header"><div><h3>Email</h3><p class="card-sub">Gmail only · used to recover your password</p></div></div>
        <p class="x-mail-cur">Current: <strong id="xMailCur">—</strong></p>
        <div class="form-field"><label>New Gmail</label><input type="email" id="xMailNew" placeholder="yourname@gmail.com"/></div>
        <div class="form-actions" style="margin-bottom:12px"><button type="button" class="auth-btn small" id="xMailSend"><i class="fas fa-paper-plane"></i> Send Code</button></div>
        <div id="xMailCodeRow" class="hidden">
          <div class="form-field"><label>6-digit code</label><input type="text" id="xMailCode" inputmode="numeric" maxlength="6" autocomplete="one-time-code"/></div>
          <div class="form-actions"><button type="button" class="auth-btn small" id="xMailVerify"><i class="fas fa-check"></i> Verify &amp; Save</button></div>
        </div>
      </div>`);
      const loadMail = async () => { const r = await acc('my_account'); if (r.success && r.data) $('xMailCur').textContent = r.data.email || 'No email set'; };
      loadMail();
      let pending = '';
      $('xPwForm').addEventListener('submit', async e => {
        e.preventDefault();
        const f = e.target;
        if (f.password.value !== f.confirm.value) { alert('Passwords do not match.'); return; }
        const r = await acc('change_password', { current: f.current.value, password: f.password.value });
        alert(r.message);
        if (r.success) f.reset();
      });
      $('xMailSend').addEventListener('click', async () => {
        const b = $('xMailSend'); b.disabled = true;
        const r = await acc('email_change_request', { email: $('xMailNew').value.trim() });
        b.disabled = false; alert(r.message);
        if (r.success) { pending = r.data.email; $('xMailCodeRow').classList.remove('hidden'); $('xMailCode').focus(); }
      });
      $('xMailVerify').addEventListener('click', async () => {
        const r = await acc('email_change_verify', { email: pending, code: $('xMailCode').value.trim() });
        alert(r.message);
        if (r.success) { $('xMailCodeRow').classList.add('hidden'); $('xMailCode').value = ''; $('xMailNew').value = ''; loadMail(); }
      });
    }
  });
})();
