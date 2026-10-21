/* Admin Panel → "Manage User Accounts" (set new password / change email). Loaded last on index.php. */
(function () {
  const css = `
  .acc-search{position:relative;margin:4px 0 12px;max-width:340px}
  .acc-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px}
  .acc-search input{width:100%;padding:9px 12px 9px 34px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:13px;outline:none}
  .acc-search input:focus{border-color:var(--sapphire-glow);box-shadow:0 0 0 3px rgba(59,130,246,.10)}
  .acc-mail{font-size:12.5px;color:var(--text-mid);word-break:break-all}
  .acc-none{color:var(--text-faint);font-style:italic;font-size:12.5px}
  .acc-back{position:fixed;inset:0;z-index:10000;background:rgba(8,16,31,.6);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:16px}
  .acc-box{background:#fff;border-radius:18px;width:100%;max-width:420px;padding:22px;box-shadow:0 24px 70px rgba(0,0,0,.4);border-top:4px solid var(--sapphire)}
  .acc-box h4{font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;margin-bottom:4px}
  .acc-box p{font-size:12.5px;color:var(--text-soft);margin-bottom:12px}
  .acc-box p b{color:var(--text-primary)}
  .acc-box input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:12px 13px;font-size:16px;outline:none}
  .acc-box input:focus{border-color:var(--sapphire-glow);box-shadow:0 0 0 3px rgba(59,130,246,.12)}
  .acc-err{font-size:12px;color:var(--crimson);font-weight:600;min-height:16px;margin-top:6px}
  .acc-act{display:flex;gap:10px;margin-top:10px}
  .acc-act button{flex:1;min-height:46px;border-radius:10px;font-size:14px;font-weight:700;border:1.5px solid var(--border);background:#fff;color:var(--text-mid)}
  .acc-act button.ok{background:var(--sapphire);border-color:var(--sapphire);color:#fff}
  .acc-act button:disabled{opacity:.6}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  async function ac(action, payload) {
    try {
      const r = await fetch('api_account.php?action=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload || {}) });
      return await r.json();
    } catch (e) { return { success: false, message: 'Network error. Please check your connection.' }; }
  }

  let USERS = [];

  function modal(title, sub, type, value, placeholder, onSave) {
    const back = document.createElement('div');
    back.className = 'acc-back';
    back.innerHTML = `<div class="acc-box" role="dialog" aria-modal="true"><h4>${escapeHtml(title)}</h4><p>${sub}</p>
      <input type="${type}" autocomplete="off" placeholder="${escapeHtml(placeholder)}"/>
      <div class="acc-err"></div>
      <div class="acc-act"><button type="button" class="cancel">Cancel</button><button type="button" class="ok">Save</button></div></div>`;
    document.body.appendChild(back);
    const inp = back.querySelector('input'), err = back.querySelector('.acc-err'), ok = back.querySelector('.ok');
    inp.value = value || '';
    setTimeout(() => inp.focus(), 50);
    const close = () => { document.removeEventListener('keydown', onKey, true); back.remove(); };
    const save = async () => {
      err.textContent = ''; ok.disabled = true;
      const res = await onSave(inp.value.trim());
      ok.disabled = false;
      if (!res.success) { err.textContent = res.message; return; }
      close(); alert(res.message); load();
    };
    const onKey = e => { if (e.key === 'Escape') close(); else if (e.key === 'Enter') { e.preventDefault(); save(); } };
    document.addEventListener('keydown', onKey, true);
    back.querySelector('.cancel').addEventListener('click', close);
    ok.addEventListener('click', save);
    back.addEventListener('mousedown', e => { if (e.target === back) close(); });
  }

  function render() {
    const q = (document.getElementById('accSearch').value || '').trim().toLowerCase();
    const list = USERS.filter(u => !q || `${u.full_name} ${u.username} ${u.email || ''}`.toLowerCase().includes(q));
    const tb = document.getElementById('accBody');
    tb.innerHTML = list.length ? list.map(u => `
      <tr>
        <td>${escapeHtml(u.full_name)}</td>
        <td>${escapeHtml(u.username)}</td>
        <td>${u.email ? `<span class="acc-mail">${escapeHtml(u.email)}</span>` : '<span class="acc-none">No email</span>'}</td>
        <td><span class="status-pill role-${escapeHtml(u.role)}">${u.role === 'secretariat' ? 'Secretariat' : 'Slot Leader'}</span></td>
        <td><span class="status-pill ${escapeHtml(u.status)}">${escapeHtml(u.status)}</span></td>
        <td>
          <button class="table-btn edit-btn" data-act="pw" data-id="${u.id}" title="Set new password"><i class="fas fa-key"></i></button>
          <button class="table-btn edit-btn" data-act="em" data-id="${u.id}" title="Change email"><i class="fas fa-envelope"></i></button>
        </td>
      </tr>`).join('') : '<tr><td colspan="6" class="empty-msg">No accounts found.</td></tr>';
  }

  async function load() {
    const res = await ac('admin_accounts');
    const tb = document.getElementById('accBody');
    if (!res.success) { tb.innerHTML = `<tr><td colspan="6" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
    USERS = res.data;
    render();
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const page = document.getElementById('page-admin');
    if (!page || typeof apiCall !== 'function') return;
    const s = await apiCall('check_session');
    if (!(s.success && s.data.role === 'secretariat')) return;

    const tabs = page.querySelector('.admin-tabs-wrap');
    const btn = document.createElement('button');
    btn.className = 'admin-tab';
    btn.dataset.adminTab = 'admin-accounts';
    btn.innerHTML = '<i class="fas fa-user-lock"></i> Manage User Accounts';
    tabs.appendChild(btn);

    const sec = document.createElement('div');
    sec.className = 'admin-section';
    sec.id = 'admin-accounts';
    sec.innerHTML = `
      <div class="card">
        <div class="card-header"><div>
          <h3>Manage User Accounts</h3>
          <p class="card-sub">Set a new password or change the email of any account</p>
        </div></div>
        <div class="acc-search"><i class="fas fa-search"></i><input type="text" id="accSearch" placeholder="Search name, username, email..." autocomplete="off"/></div>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Full Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="accBody"><tr><td colspan="6" class="empty-msg">Loading...</td></tr></tbody>
        </table></div>
      </div>`;
    page.appendChild(sec);

    btn.addEventListener('click', () => {
      page.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
      page.querySelectorAll('.admin-section').forEach(x => x.classList.remove('active'));
      btn.classList.add('active'); sec.classList.add('active');
      load();
    });
    document.getElementById('accSearch').addEventListener('input', render);
    document.getElementById('accBody').addEventListener('click', e => {
      const b = e.target.closest('[data-act]');
      if (!b) return;
      const u = USERS.find(x => String(x.id) === b.dataset.id);
      if (!u) return;
      if (b.dataset.act === 'pw') {
        modal('Set New Password', `New password for <b>${escapeHtml(u.full_name)}</b> (${escapeHtml(u.username)}). Minimum 6 characters.`,
          'text', '', 'New password', v => ac('admin_set_password', { id: u.id, password: v }));
      } else {
        modal('Change Email', `Gmail for <b>${escapeHtml(u.full_name)}</b>. Leave blank to remove it.`,
          'email', u.email || '', 'yourname@gmail.com', v => ac('admin_set_email', { id: u.id, email: v }));
      }
    });
  });
})();
