/* ============================================================
   PYM — Commission On Youth + Slot-leader member picker
   Load AFTER app.js:  <script src="coy.js"></script>
   ============================================================ */

(function injectCoyStyles() {
  const css = `
  .coy-card{margin-bottom:20px;border-top:3px solid var(--gold-mid)}
  .coy-section-title{font-size:13.5px;font-weight:800;color:var(--text-primary);margin:6px 0 12px;display:flex;align-items:center;gap:8px}
  .coy-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin-bottom:20px}
  .coy-pos{border:1.5px solid var(--border);border-radius:var(--radius);padding:13px 14px;background:var(--surface-2);display:flex;flex-direction:column;gap:9px}
  .coy-pos-head{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:13px}
  .coy-pos-head strong{font-weight:800;color:var(--text-primary)}
  .coy-tools{display:flex;gap:4px}
  .coy-tools .table-btn{width:26px;height:26px;font-size:10.5px;margin:0}
  .coy-chips{display:flex;flex-wrap:wrap;gap:6px;min-height:26px}
  .coy-chip{display:inline-flex;align-items:center;gap:6px;background:var(--sapphire-pale);border:1px solid #BFDBFE;color:var(--sapphire);font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px}
  .coy-x{border:none;background:none;color:var(--crimson);font-size:14px;line-height:1;font-weight:800;cursor:pointer;padding:0}
  .coy-empty{font-size:11.5px;color:var(--text-faint);font-style:italic;padding-top:4px}
  .coy-add{display:flex;gap:6px}
  .coy-add select{flex:1;min-width:0;border:1.5px solid var(--border);border-radius:8px;padding:7px 28px 7px 10px;font-size:12px;background-color:var(--surface);outline:none}
  .coy-add button{border:none;border-radius:8px;background:var(--ink);color:#fff;font-size:11.5px;font-weight:700;padding:0 12px}
  .coy-add button:hover{background:var(--sapphire)}
  .coy-picker-note{font-size:11.5px;color:var(--text-soft);margin:2px 0 10px}
  .coy-picker-search{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:13.5px;margin-bottom:10px;outline:none}
  .coy-picker-search:focus{border-color:var(--sapphire-glow)}
  #pickerList{max-height:280px;margin-bottom:12px}
  .coy-combo{position:relative;flex:1;min-width:0}
  .coy-combo-input{width:100%;border:1.5px solid var(--border);border-radius:8px;padding:7px 10px;font-size:12px;background:var(--surface);outline:none}
  .coy-combo-input:focus{border-color:var(--sapphire-glow);box-shadow:0 0 0 3px rgba(59,130,246,.10)}
  .coy-combo-list{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:30;max-height:210px;overflow-y:auto;background:var(--surface);border:1.5px solid var(--border-mid);border-radius:8px;box-shadow:var(--shadow-md);padding:4px}
  .coy-combo-opt{padding:7px 10px;font-size:12.5px;border-radius:6px;cursor:pointer}
  .coy-combo-opt:hover,.coy-combo-opt.active{background:var(--sapphire-pale);color:var(--sapphire)}
  .coy-combo-empty{padding:9px 10px;font-size:12px;color:var(--text-faint);font-style:italic}
  .youth-search{position:relative;margin:4px 0 12px;max-width:340px}
  .youth-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px}
  .youth-search input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:13px;outline:none}
  .youth-search input:focus{border-color:var(--sapphire-glow);box-shadow:0 0 0 3px rgba(59,130,246,.10)}
  `;
  const el = document.createElement('style');
  el.textContent = css;
  document.head.appendChild(el);
})();

let COY_YOUTH = [];

async function coyApi(action, payload = null) {
  try {
    const res = await fetch(`api_coy.php?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    });
    return await res.json();
  } catch (err) {
    return { success: false, message: 'Network error. Please check your connection.' };
  }
}

/* ------------------------------------------------------------
   Build UI (COY card + member picker) once DOM is ready
   ------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', async () => {
  const page = document.getElementById('page-youth');
  if (!page) return;

  // Commission On Youth card — upper part of Youth Members
  const coy = document.createElement('div');
  coy.className = 'card coy-card';
  coy.id = 'coyCard';
  coy.innerHTML = `
    <div class="card-header">
      <div>
        <h3><i class="fas fa-star" style="color:var(--gold-mid);margin-right:8px"></i>Commission On Youth</h3>
        <p class="card-sub">Leaders of every time slot · members must be registered Youth Members</p>
      </div>
      <button class="add-btn hidden" id="coyAddPosBtn"><i class="fas fa-plus"></i> Add Position</button>
    </div>
    <div id="coyBody"><p class="empty-msg">Loading...</p></div>`;
  page.insertBefore(coy, page.firstChild);

  // Picker for slot leaders — placed under the youth form
  const form = document.getElementById('youthForm');
  const picker = document.createElement('div');
  picker.id = 'youthPicker';
  picker.className = 'data-form hidden';
  picker.innerHTML = `
    <strong style="font-size:14px">Add from registered Youth Members</strong>
    <p class="coy-picker-note">Only registered youth without a slot are listed. Slot leaders and Commission On Youth are not included.</p>
    <input type="text" id="pickerSearch" class="coy-picker-search" placeholder="Search name..."/>
    <div class="leader-checklist" id="pickerList"></div>
    <div class="form-actions">
      <button type="button" class="auth-btn small" id="pickerAddBtn"><i class="fas fa-user-plus"></i> Add to my slot</button>
      <button type="button" class="cancel-btn" id="pickerCancelBtn">Cancel</button>
    </div>`;
  form.parentNode.insertBefore(picker, form.nextSibling);

  document.getElementById('pickerCancelBtn').addEventListener('click', () => picker.classList.add('hidden'));
  document.getElementById('pickerSearch').addEventListener('input', (e) => {
    const q = e.target.value.trim().toLowerCase();
    document.querySelectorAll('#pickerList .leader-check').forEach(l => {
      l.style.display = l.dataset.name.includes(q) ? '' : 'none';
    });
  });
  document.getElementById('pickerAddBtn').addEventListener('click', async () => {
    const ids = Array.from(document.querySelectorAll('#pickerList input:checked')).map(i => i.value);
    if (!ids.length) { alert('Select at least one member.'); return; }
    const res = await coyApi('assign_youth_slot', { member_ids: ids });
    alert(res.message);
    if (res.success) { picker.classList.add('hidden'); loadYouth(); loadDashboardStats(); }
  });

  document.getElementById('coyAddPosBtn').addEventListener('click', async () => {
    const name = prompt('New position name:');
    if (!name || !name.trim()) return;
    const res = await coyApi('save_coy_position', { name: name.trim() });
    if (!res.success) alert(res.message);
    loadCOY();
  });

  coy.addEventListener('click', onCoyClick);

  // Real-time searchable member combobox
  const showCombo = (combo) => {
    const input = combo.querySelector('.coy-combo-input');
    const list = combo.querySelector('.coy-combo-list');
    const q = input.value.trim().toLowerCase();
    const taken = (combo.dataset.taken || '').split(',');
    const items = COY_YOUTH.filter(y => !taken.includes(String(y.id)) && y.full_name.toLowerCase().includes(q)).slice(0, 50);
    list.innerHTML = items.length
      ? items.map((y, i) => `<div class="coy-combo-opt${i === 0 ? ' active' : ''}" data-id="${y.id}" data-name="${escapeHtml(y.full_name)}">${escapeHtml(y.full_name)}</div>`).join('')
      : '<div class="coy-combo-empty">No match</div>';
    list.classList.remove('hidden');
  };
  const pickOpt = (combo, opt) => {
    combo.dataset.value = opt.dataset.id;
    combo.querySelector('.coy-combo-input').value = opt.dataset.name;
    combo.querySelector('.coy-combo-list').classList.add('hidden');
  };
  coy.addEventListener('input', (e) => {
    const combo = e.target.closest('.coy-combo');
    if (!combo) return;
    combo.dataset.value = '';
    showCombo(combo);
  });
  coy.addEventListener('focusin', (e) => {
    const combo = e.target.closest('.coy-combo');
    if (combo) showCombo(combo);
  });
  coy.addEventListener('mousedown', (e) => {
    const opt = e.target.closest('.coy-combo-opt');
    if (opt) { e.preventDefault(); pickOpt(opt.closest('.coy-combo'), opt); }
  });
  coy.addEventListener('keydown', (e) => {
    const combo = e.target.closest('.coy-combo');
    if (!combo) return;
    const opts = Array.from(combo.querySelectorAll('.coy-combo-opt'));
    let i = opts.findIndex(o => o.classList.contains('active'));
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!opts.length) return;
      opts[i] && opts[i].classList.remove('active');
      i = e.key === 'ArrowDown' ? Math.min(opts.length - 1, i + 1) : Math.max(0, i - 1);
      opts[i].classList.add('active'); opts[i].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (opts[i]) pickOpt(combo, opts[i]);
    } else if (e.key === 'Escape') {
      combo.querySelector('.coy-combo-list').classList.add('hidden');
    }
  });
  document.addEventListener('click', (e) => {
    document.querySelectorAll('.coy-combo-list').forEach(l => {
      if (!l.parentElement.contains(e.target)) l.classList.add('hidden');
    });
  });

  // Real-time search for the Youth Members table
  const tableWrap = page.querySelector('.card:not(.coy-card) .table-wrap');
  if (tableWrap) {
    const sb = document.createElement('div');
    sb.className = 'youth-search';
    sb.innerHTML = '<i class="fas fa-search"></i><input type="text" id="youthSearch" placeholder="Search youth members..." autocomplete="off"/>';
    const tools = document.createElement('div');
    tools.style.cssText = 'display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:4px 0 12px';
    tableWrap.parentNode.insertBefore(tools, tableWrap);
    sb.style.margin = '0';
    tools.appendChild(sb);
    const sf = document.createElement('select');
    sf.id = 'youthScholarFilter';
    sf.setAttribute('aria-label', 'Filter by scholar category');
    sf.style.cssText = 'padding:9px 32px 9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:13px;background-color:var(--surface);outline:none;max-width:100%';
    sf.innerHTML = '<option value="">All categories</option><option value="none">Non Scholar</option><option value="shs">Senior High School Scholar</option><option value="college">College Scholar</option>';
    tools.appendChild(sf);
    sf.addEventListener('change', applyYouthFilter);
    sb.querySelector('input').addEventListener('input', applyYouthFilter);
  }

  // Leaders can't bulk-create new members
  const s = await apiCall('check_session');
  if (s.success && s.data.role === 'secretariat') {
    // Events and Groups/Slots are managed inside the Admin Panel
    ['events', 'groups'].forEach(p => {
      const nav = document.querySelector(`.nav-item[data-page="${p}"]`);
      if (nav) nav.style.display = 'none';
    });
    const sec = Array.from(document.querySelectorAll('.nav-section')).find(n => n.textContent.trim() === 'Ministry');
    if (sec) sec.style.display = 'none';
  }
});

/* ------------------------------------------------------------
   Commission On Youth
   ------------------------------------------------------------ */
async function loadCOY() {
  const body = document.getElementById('coyBody');
  if (!body) return;
  const sec = isSecretariatUser();
  const addPosBtn = document.getElementById('coyAddPosBtn');
  if (addPosBtn) addPosBtn.classList.toggle('hidden', !sec);

  const [res, yRes] = await Promise.all([
    coyApi('get_coy'),
    sec ? coyApi('get_all_youth') : Promise.resolve({ success: true, data: [] })
  ]);
  if (!res.success) { body.innerHTML = `<p class="empty-msg">${escapeHtml(res.message)}</p>`; return; }
  const youth = yRes.success ? yRes.data : [];
  COY_YOUTH = youth;

  const chips = (list, act) => list.length
    ? list.map(m => `<span class="coy-chip">${escapeHtml(m.full_name)}${sec ? `<button class="coy-x" data-act="${act}" data-id="${m.id}" title="Remove">&times;</button>` : ''}</span>`).join('')
    : '<span class="coy-empty">Vacant</span>';

  const picker = (key, list, act, attr, id) => {
    if (!sec) return '';
    const taken = list.map(m => String(m.member_id));
    return `<div class="coy-add">
      <div class="coy-combo" data-sel="${key}" data-taken="${taken.join(',')}" data-value="">
        <input type="text" class="coy-combo-input" placeholder="Search member..." autocomplete="off"/>
        <div class="coy-combo-list hidden"></div>
      </div>
      <button data-act="${act}" ${attr}="${id}">Add</button>
    </div>`;
  };

  body.innerHTML = `
    <div class="coy-section-title"><i class="fas fa-user-tie" style="color:var(--sapphire)"></i> Officers</div>
    <div class="coy-grid">
      ${res.data.positions.length ? res.data.positions.map(p => `
        <div class="coy-pos">
          <div class="coy-pos-head">
            <strong>${escapeHtml(p.name)}</strong>
            ${sec ? `<div class="coy-tools">
              <button class="table-btn edit-btn" data-act="rename-pos" data-id="${p.id}" data-name="${escapeHtml(p.name)}" title="Rename"><i class="fas fa-pen"></i></button>
              <button class="table-btn delete-btn" data-act="del-pos" data-id="${p.id}" title="Remove position"><i class="fas fa-trash"></i></button>
            </div>` : ''}
          </div>
          <div class="coy-chips">${chips(p.holders, 'rm-holder')}</div>
          ${p.accounts && p.accounts.length ? `<div style="font-size:11px;color:var(--text-soft)"><i class="fas fa-user-shield"></i> Account: ${p.accounts.map(a => escapeHtml(a.full_name)).join(', ')}</div>` : ''}
          ${picker('pos-' + p.id, p.holders, 'add-holder', 'data-pos', p.id)}
        </div>`).join('') : '<p class="empty-msg">No positions.</p>'}
    </div>

    <div class="coy-section-title"><i class="fas fa-people-group" style="color:var(--emerald)"></i> Committees</div>
    <div class="coy-grid">
      ${res.data.committees.map(c => `
        <div class="coy-pos">
          <div class="coy-pos-head"><strong>${escapeHtml(c.name)}</strong><small style="color:var(--text-soft)">${c.members.length} member(s)</small></div>
          <div class="coy-chips">${chips(c.members, 'rm-cm').replace(/Vacant/g, 'No members yet')}</div>
          ${picker('com-' + c.id, c.members, 'add-cm', 'data-com', c.id)}
        </div>`).join('')}
    </div>`;
}

async function onCoyClick(e) {
  const btn = e.target.closest('[data-act]');
  if (!btn) return;
  const act = btn.dataset.act;
  let res = null;

  if (act === 'rename-pos') {
    const name = prompt('Position name:', btn.dataset.name);
    if (!name || !name.trim()) return;
    res = await coyApi('save_coy_position', { id: btn.dataset.id, name: name.trim() });
  } else if (act === 'del-pos') {
    if (!confirm('Remove this position?')) return;
    res = await coyApi('delete_coy_position', { id: btn.dataset.id });
  } else if (act === 'rm-holder') {
    res = await coyApi('remove_coy_holder', { id: btn.dataset.id });
  } else if (act === 'rm-cm') {
    res = await coyApi('remove_committee_member', { id: btn.dataset.id });
  } else if (act === 'add-holder' || act === 'add-cm') {
    const isPos = act === 'add-holder';
    const key = isPos ? 'pos-' + btn.dataset.pos : 'com-' + btn.dataset.com;
    const sel = document.querySelector(`#coyCard .coy-combo[data-sel="${key}"]`);
    if (!sel || !sel.dataset.value) { alert('Select a youth member first.'); return; }
    res = isPos
      ? await coyApi('add_coy_holder', { position_id: btn.dataset.pos, member_id: sel.dataset.value })
      : await coyApi('add_committee_member', { committee_id: btn.dataset.com, member_id: sel.dataset.value });
  }

  if (res && !res.success) alert(res.message);
  loadCOY();
}

/* ------------------------------------------------------------
   Users & Roles — Commission On Youth positions as roles
   ------------------------------------------------------------ */
let USER_POS = {};
let COY_POSITIONS = [];

async function refreshPositionOptions() {
  const sel = document.getElementById('userPositionSelect');
  const res = await coyApi('get_coy');
  COY_POSITIONS = res.success ? res.data.positions : [];
  if (sel) {
    sel.innerHTML = '<option value="">— No position —</option>' +
      COY_POSITIONS.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
  }
}

async function decorateUsers() {
  if (!isSecretariatUser()) return;
  const res = await coyApi('get_user_positions');
  USER_POS = {};
  if (res.success) res.data.forEach(r => { USER_POS[String(r.id)] = r; });

  const table = document.querySelector('#usersTableBody').closest('table');
  const headRow = table.querySelector('thead tr');
  if (!headRow.querySelector('.pos-th')) {
    const th = document.createElement('th');
    th.className = 'pos-th';
    th.textContent = 'Roles';
    headRow.insertBefore(th, headRow.children[3]);
  }
  document.querySelectorAll('#usersTableBody tr').forEach(tr => {
    const btn = tr.querySelector('.edit-btn');
    if (!btn || tr.querySelector('.pos-td')) return;
    const td = document.createElement('td');
    td.className = 'pos-td';
    const r = USER_POS[String(btn.dataset.id)];
    const pills = [];
    if (r) {
      if (r.role === 'secretariat') pills.push('<span class="status-pill role-secretariat">Secretariat / Admin</span>');
      if (r.slot_name) pills.push(`<span class="status-pill role-leader">Slot Leader · ${escapeHtml(r.slot_name)}</span>`);
      if (r.position_name) pills.push(`<span class="status-pill active">${escapeHtml(r.position_name)}</span>`);
    }
    td.innerHTML = pills.length ? `<div style="display:flex;flex-wrap:wrap;gap:4px">${pills.join('')}</div>` : '<span style="color:var(--text-faint)">—</span>';
    tr.insertBefore(td, tr.children[3]);
  });
}

(function wrapUsers() {
  const origLoadUsers = loadUsers;
  loadUsers = async function () {
    await origLoadUsers();
    await decorateUsers();
  };
  const origEditUser = editUser;
  editUser = async function (id) {
    origEditUser(id);
    await refreshPositionOptions();
    const sel = document.getElementById('userPositionSelect');
    const r = USER_POS[String(id)];
    if (sel) sel.value = r && r.position_id ? String(r.position_id) : '';
  };
})();

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('userForm');
  if (!form) return;

  // Position field inside the user form
  const field = document.createElement('div');
  field.className = 'form-row';
  field.innerHTML = `
    <div class="form-field">
      <label>Position <small>(Commission On Youth)</small></label>
      <select id="userPositionSelect"><option value="">— No position —</option></select>
    </div>`;
  form.querySelector('.form-actions').before(field);

  document.getElementById('showUserFormBtn').addEventListener('click', refreshPositionOptions);
  form.addEventListener('click', (e) => { if (e.target.closest('.edit-btn')) refreshPositionOptions(); });

  // Capture-phase submit: save user, then save position
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    e.stopImmediatePropagation();
    const data = {
      id: form.id.value, full_name: form.full_name.value.trim(),
      username: form.username.value.trim(), password: form.password.value,
      role: form.role.value, status: form.status.value
    };
    const res = await apiCall('save_user', data);
    if (res.success) {
      await coyApi('set_user_position', {
        user_id: form.id.value || 0, username: data.username,
        position_id: document.getElementById('userPositionSelect').value || null
      });
    }
    alert(res.message);
    if (res.success) { form.reset(); form.classList.add('hidden'); loadUsers(); }
  }, true);

  // Sidebar badge shows the account's position
  (async () => {
    const r = await coyApi('get_my_position');
    if (!r.success || !r.data) return;
    const badge = document.getElementById('sidebarUserRole');
    for (let i = 0; i < 15; i++) {
      if (badge.textContent && badge.textContent !== '—') break;
      await new Promise(ok => setTimeout(ok, 300));
    }
    badge.textContent = badge.textContent + ' · ' + r.data.name;
  })();
});

/* ------------------------------------------------------------
   Youth Members — overrides of app.js functions
   ------------------------------------------------------------ */
function initYouthPage() {
  const showBtn = document.getElementById('showYouthFormBtn');
  const form = document.getElementById('youthForm');
  const cancelBtn = document.getElementById('cancelYouthFormBtn');

  showBtn.addEventListener('click', () => {
    if (!isSecretariatUser()) { openYouthPicker(); return; }  // leaders: pick existing only
    form.reset(); form.id.value = ''; form.dataset.member = '';
    form.classList.remove('hidden');
  });
  cancelBtn.addEventListener('click', () => { form.classList.add('hidden'); form.reset(); });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    let data = { id: form.id.value, full_name: form.full_name.value.trim(), slot_id: '', status: form.status.value, scholar_type: form.elements['scholar_type'].value };
    if (form.id.value && form.dataset.member) {
      // keep the existing slot/contact data when editing
      data = Object.assign(JSON.parse(form.dataset.member), data, { slot_id: JSON.parse(form.dataset.member).slot_id || '' });
    }
    const res = await apiCall('save_youth', data);
    alert(res.message);
    if (res.success) { form.reset(); form.classList.add('hidden'); loadYouth(); }
  });
}

async function openYouthPicker() {
  const box = document.getElementById('youthPicker');
  const list = document.getElementById('pickerList');
  document.getElementById('youthForm').classList.add('hidden');
  document.getElementById('pickerSearch').value = '';
  box.classList.remove('hidden');
  list.innerHTML = '<p class="empty-msg">Loading...</p>';

  const res = await coyApi('get_available_youth');
  if (!res.success) { list.innerHTML = `<p class="empty-msg">${escapeHtml(res.message)}</p>`; return; }
  list.innerHTML = res.data.length
    ? res.data.map(m => `
      <label class="leader-check" data-name="${escapeHtml(m.full_name.toLowerCase())}">
        <input type="checkbox" value="${m.id}"/>
        <span>${escapeHtml(m.full_name)}</span>
      </label>`).join('')
    : '<p class="empty-msg">No available registered youth.</p>';
}

async function loadYouth() {
  loadCOY();
  const res = await apiCall('get_youth');
  const tbody = document.getElementById('youthTableBody');
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
  if (res.data.length === 0) { tbody.innerHTML = '<tr><td colspan="5" class="empty-msg">No youth members yet.</td></tr>'; return; }

  const sec = isSecretariatUser();
  tbody.innerHTML = res.data.map(m => `
    <tr data-scholar="${escapeHtml(m.scholar_type || 'none')}">
      <td>${escapeHtml(m.full_name)}</td>
      <td>${m.slot_name ? `<span class="slot-dot" style="display:inline-block;background:${escapeHtml(m.slot_color)}"></span> ${escapeHtml(m.slot_name)}` : '<span style="color:var(--text-light)">— None —</span>'}</td>
      <td>${scholarBadge(m.scholar_type)}</td>
      <td><span class="status-pill ${m.status}">${m.status}</span></td>
      <td>${sec
        ? `<button class="table-btn edit-btn" data-id="${m.id}" title="Edit"><i class="fas fa-pen"></i></button>
           <button class="table-btn delete-btn" data-id="${m.id}" title="Delete"><i class="fas fa-trash"></i></button>`
        : `<button class="table-btn delete-btn" data-id="${m.id}" title="Remove from my slot"><i class="fas fa-user-minus"></i></button>`}
      </td>
    </tr>
  `).join('');

  tbody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => editYouth(btn.dataset.id, res.data)));
  tbody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => deleteYouth(btn.dataset.id)));
  applyYouthFilter();
}

function editYouth(id, list) {
  const m = list.find(x => String(x.id) === String(id));
  if (!m) return;
  const form = document.getElementById('youthForm');
  document.getElementById('youthPicker').classList.add('hidden');
  form.id.value = m.id;
  form.full_name.value = m.full_name;
  form.status.value = m.status;
  form.elements['scholar_type'].value = m.scholar_type || 'none';
  form.dataset.member = JSON.stringify(m);
  form.classList.remove('hidden');
  form.scrollIntoView({ behavior: 'smooth' });
}

async function deleteYouth(id) {
  if (isSecretariatUser()) {
    const row = document.querySelector('#youthTableBody .delete-btn[data-id="' + id + '"]');
    const nm = row ? row.closest('tr').children[0].textContent.trim() : '';
    if (!askTypeName(nm, 'this member and their attendance')) return;
    const res = await apiCall('delete_youth', { id });
    alert(res.message);
    if (res.success) loadYouth();
  } else {
    if (!confirm('Remove this member from your slot? They stay registered in Youth Members.')) return;
    const res = await coyApi('unassign_youth', { id });
    alert(res.message);
    if (res.success) { loadYouth(); loadDashboardStats(); }
  }
}


function applyYouthFilter() {
  const inp = document.getElementById('youthSearch');
  if (!inp) return;
  const q = inp.value.trim().toLowerCase();
  const sfEl = document.getElementById('youthScholarFilter');
  const sk = sfEl ? sfEl.value : '';
  const tbody = document.getElementById('youthTableBody');
  tbody.querySelectorAll('.no-match-row').forEach(r => r.remove());
  const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.querySelector('.edit-btn, .delete-btn'));
  let shown = 0;
  rows.forEach(r => {
    const ok = (!q || r.textContent.toLowerCase().includes(q)) && (!sk || r.dataset.scholar === sk);
    r.style.display = ok ? '' : 'none';
    if (ok) shown++;
  });
  if (rows.length && !shown) tbody.insertAdjacentHTML('beforeend', '<tr class="no-match-row"><td colspan="5" class="empty-msg">No matching members.</td></tr>');
}
