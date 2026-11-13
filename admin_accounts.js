/* Admin Panel → "Manage User Accounts" (set new password / change email). Loaded last on index.php.
   + Users & Roles: delete window (single + bulk) that removes the account AND all its data. */
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

/* ============================================================
   Users & Roles — DELETE window (single + bulk), same concept as Youth Members.
   Deletes the account AND everything tied to it (linked youth record + all attendance).
   Uses the .yd-* / .yb-* styles that adminevents.js already injects.
   ============================================================ */
(function () {
  const $ = id => document.getElementById(id);
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

  /* Confirmation window. Resolves true only when DELETE is typed. */
  function askDelete(names) {
    const count = names.length, one = count === 1;
    return new Promise(resolve => {
      const back = document.createElement('div');
      back.className = 'yd-back';
      back.innerHTML =
        '<div class="yd-box" role="dialog" aria-modal="true" aria-labelledby="udTitle">' +
          '<div class="yd-pane" data-pane="main">' +
            '<div class="yd-body">' +
              '<div class="yd-head"><div class="yd-ic"><i class="fas fa-user-xmark"></i></div>' +
                '<div><h4 class="yd-title" id="udTitle">' + (one ? 'Delete ' + esc(names[0]) + '?' : 'Delete ' + count + ' accounts?') + '</h4>' +
                '<p class="yd-sub">' + (one ? 'This account will lose access and all its data will be removed.' : 'These accounts will lose access and all their data will be removed.') + '</p></div></div>' +
              '<ul class="yd-facts">' +
                '<li><i class="fas fa-user-slash i-lock" style="color:#DC2626"></i><span>The <b>login account</b> and its <b>linked youth member record</b> are deleted.</span></li>' +
                '<li><i class="fas fa-clipboard-list i-bin"></i><span><b>All attendance</b> (slots and events), server and leader roles are deleted too.</span></li>' +
                '<li><i class="fas fa-clock-rotate-left i-clock"></i><span>Backed up in the <b>Recycle Bin</b>: restore within <b>30 days</b> from Admin Panel.</span></li>' +
              '</ul>' +
              (one ? '' : '<button type="button" class="yd-view"><span class="yd-av"><i class="fas fa-users"></i></span><span>View selected accounts<small>Review and search the ' + count + ' accounts</small></span><i class="fas fa-chevron-right yd-chev"></i></button>') +
              '<label class="yd-label" for="udInput">Type <b>DELETE</b> to confirm</label>' +
              '<input id="udInput" class="yd-input" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="DELETE"/>' +
            '</div>' +
            '<div class="yd-foot"><button type="button" class="cancel">Cancel</button><button type="button" class="go" disabled><i class="fas fa-trash"></i> Delete' + (one ? '' : ' ' + count) + '</button></div>' +
          '</div>' +
          (one ? '' :
          '<div class="yd-pane" data-pane="names" hidden>' +
            '<div class="yd-nh"><button type="button" class="yd-back-btn" aria-label="Back"><i class="fas fa-arrow-left"></i></button>' +
              '<div><h4>Selected accounts</h4><p>' + count + ' accounts will be deleted</p></div></div>' +
            '<div class="yd-sbox"><i class="fas fa-search"></i><input type="text" placeholder="Search a name" autocomplete="off" spellcheck="false" aria-label="Search selected accounts"/><button type="button" class="yd-clr" aria-label="Clear search" hidden><i class="fas fa-times"></i></button></div>' +
            '<div class="yd-count" aria-live="polite"></div>' +
            '<ul class="yd-list"></ul>' +
            '<div class="yd-foot"><button type="button" class="back2">Back to confirm</button></div>' +
          '</div>') +
        '</div>';
      document.body.appendChild(back);

      const main = back.querySelector('[data-pane="main"]'), pn = back.querySelector('[data-pane="names"]');
      const inp = back.querySelector('#udInput'), go = back.querySelector('.go');
      setTimeout(() => inp.focus(), 80);

      const valid = () => inp.value.trim().toUpperCase() === 'DELETE';
      const done = v => { document.removeEventListener('keydown', onKey, true); back.remove(); resolve(v); };
      const inNames = () => pn && !pn.hidden;

      let sIn, clr, list, cnt;
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
      function openNames() { main.hidden = true; pn.hidden = false; sIn.value = ''; drawList(); setTimeout(() => sIn.focus(), 40); }
      function closeNames() { pn.hidden = true; main.hidden = false; setTimeout(() => inp.focus(), 40); }

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
      back.addEventListener('mousedown', e => { if (e.target === back) done(false); });

      if (!one) {
        sIn = pn.querySelector('.yd-sbox input'); clr = pn.querySelector('.yd-clr');
        list = pn.querySelector('.yd-list'); cnt = pn.querySelector('.yd-count');
        back.querySelector('.yd-view').addEventListener('click', openNames);
        pn.querySelector('.yd-back-btn').addEventListener('click', closeNames);
        pn.querySelector('.back2').addEventListener('click', closeNames);
        sIn.addEventListener('input', drawList);
        clr.addEventListener('click', () => { sIn.value = ''; drawList(); sIn.focus(); });
      }
    });
  }

  async function deleteApi(ids) {
    try {
      const r = await fetch('api_bulkusers.php?action=bulk_delete_users', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids }) });
      return await r.json();
    } catch (e) { return { success: false, message: 'Network error. Please check your connection.' }; }
  }

  let ON = false, ME = 0;
  const SEL = new Set();

  const userName = id => {
    const u = (typeof ALL_USERS !== 'undefined' ? ALL_USERS : []).find(x => String(x.id) === String(id));
    return u ? u.full_name : 'Account #' + id;
  };
  const rows = () => Array.from(document.querySelectorAll('#usersTableBody tr')).filter(r => r.querySelector('.ub-cb'));

  function refreshBar() {
    const bar = $('ubBar');
    if (!bar) return;
    const all = rows(), n = SEL.size;
    bar.querySelector('.yb-count').textContent = n ? n + ' account(s) selected' : 'Tick the accounts you want to delete';
    const del = bar.querySelector('.ub-del');
    del.disabled = !n;
    del.innerHTML = '<i class="fas fa-trash"></i> Delete selected' + (n ? ' (' + n + ')' : '');
    const a = bar.querySelector('.ub-all');
    a.checked = all.length > 0 && n === all.length;
    a.indeterminate = n > 0 && n < all.length;
  }

  function decorate() {
    const btn = $('ubToggle');
    if (btn) btn.classList.toggle('on', ON);
    const old = $('ubBar'); if (old) old.remove();
    if (!ON) return;
    const tb = $('usersTableBody');
    if (!tb) return;
    const present = new Set();
    Array.from(tb.querySelectorAll('tr')).forEach(r => {
      const d = r.querySelector('.delete-btn[data-id]');
      if (!d) return;
      const id = String(d.dataset.id), td = r.cells[0];
      if (Number(id) === ME) {
        SEL.delete(id);
        if (!td.querySelector('.yb-lock')) td.insertAdjacentHTML('afterbegin', '<i class="fas fa-lock yb-lock" title="This is your own account"></i>');
        return;
      }
      present.add(id);
      if (td.querySelector('.ub-cb')) return;
      td.insertAdjacentHTML('afterbegin', '<input type="checkbox" class="yb-cb ub-cb" value="' + id + '"' + (SEL.has(id) ? ' checked' : '') + ' aria-label="Select account"/>');
      r.classList.toggle('yb-sel', SEL.has(id));
    });
    Array.from(SEL).forEach(id => { if (!present.has(id)) SEL.delete(id); });
    const bar = document.createElement('div');
    bar.id = 'ubBar';
    bar.className = 'yb-bar';
    bar.innerHTML = '<label><input type="checkbox" class="ub-all"/> Select all</label><span class="yb-count"></span>' +
      '<button type="button" class="yb-del ub-del" disabled><i class="fas fa-trash"></i> Delete selected</button>' +
      '<button type="button" class="yb-cancel ub-cancel">Cancel</button>' +
      '<span class="yb-hint"><i class="fas fa-lock"></i> Your own account is locked. Deleting an account also deletes its youth record and all attendance.</span>';
    tb.closest('.table-wrap').before(bar);
    refreshBar();
  }

  function stop() { ON = false; SEL.clear(); }

  async function afterDelete(res) {
    alert(res.message);
    try { await loadUsers(); } catch (_) {}
    try { if (typeof loadYouth === 'function') loadYouth(); } catch (_) {}
    try { if (typeof loadDashboardStats === 'function') loadDashboardStats(); } catch (_) {}
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const page = $('page-users');
    if (!page || typeof apiCall !== 'function' || typeof loadUsers !== 'function') return;
    const s = await apiCall('check_session');
    if (!(s.success && s.data.role === 'secretariat')) return;
    ME = Number(s.data.id);

    // "Bulk delete" button next to "Add User"
    const addBtn = $('showUserFormBtn');
    if (addBtn) {
      const wrap = document.createElement('div');
      wrap.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;align-items:center';
      addBtn.before(wrap);
      const btn = document.createElement('button');
      btn.type = 'button'; btn.id = 'ubToggle'; btn.className = 'yb-toggle';
      btn.title = 'Bulk delete accounts (with all their data)';
      btn.innerHTML = '<i class="fas fa-list-check"></i> Bulk delete';
      wrap.appendChild(btn);
      wrap.appendChild(addBtn);
      btn.addEventListener('click', () => { if (ON) stop(); else { ON = true; SEL.clear(); } loadUsers(); });
    }

    // Re-draw checkboxes every time the table reloads
    const _lu = loadUsers;
    loadUsers = async function () {
      await _lu.apply(this, arguments);
      try { decorate(); } catch (e) { console.error(e); }
    };

    const tb = $('usersTableBody');

    // Single delete (trash icon) → same window, replaces the old prompt
    tb.addEventListener('click', async e => {
      const b = e.target.closest('.delete-btn');
      if (!b) return;
      e.preventDefault(); e.stopImmediatePropagation();
      const id = Number(b.dataset.id);
      if (id === ME) { alert('You cannot delete your own account.'); return; }
      const ok = await askDelete([userName(id)]);
      if (!ok) return;
      b.disabled = true;
      const res = await deleteApi([id]);
      b.disabled = false;
      await afterDelete(res);
    }, true);

    tb.addEventListener('change', e => {
      const c = e.target.closest('.ub-cb');
      if (!c) return;
      if (c.checked) SEL.add(c.value); else SEL.delete(c.value);
      c.closest('tr').classList.toggle('yb-sel', c.checked);
      refreshBar();
    });

    page.addEventListener('change', e => {
      if (!e.target.classList.contains('ub-all')) return;
      rows().forEach(r => {
        const cb = r.querySelector('.ub-cb');
        cb.checked = e.target.checked;
        r.classList.toggle('yb-sel', cb.checked);
        if (cb.checked) SEL.add(cb.value); else SEL.delete(cb.value);
      });
      refreshBar();
    });

    page.addEventListener('click', async e => {
      if (e.target.closest('.ub-cancel')) { stop(); loadUsers(); return; }
      const del = e.target.closest('.ub-del');
      if (!del || !SEL.size) return;
      const ids = Array.from(SEL).map(Number).filter(id => id !== ME);
      if (!ids.length) return;
      const ok = await askDelete(ids.map(userName));
      if (!ok) return;
      del.disabled = true;
      const res = await deleteApi(ids);
      if (res.success) stop();
      await afterDelete(res);
    });
  });
})();
