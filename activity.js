/* Admin Panel → "Activity Log" and "Recycle Bin" tabs (Secretariat only). Loaded last on index.php. */
(function () {
  const css = `.act-bar{display:flex;gap:10px;flex-wrap:wrap;margin:4px 0 12px}
  .act-bar input{flex:1;min-width:200px;max-width:360px;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:13px;outline:none}
  .act-bar input:focus{border-color:var(--sapphire-glow)}
  .act-det{white-space:normal!important;min-width:260px;max-width:520px;font-size:12.5px;color:var(--text-mid);line-height:1.5}
  .act-tag{display:inline-block;font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:99px;background:var(--sapphire-pale);color:var(--sapphire);text-transform:uppercase;letter-spacing:.3px}
  .act-tag.del{background:var(--crimson-pale);color:var(--crimson)}.act-tag.ok{background:var(--emerald-pale);color:var(--emerald)}`;
  const st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  const when = s => { if (!s) return '—'; const [d, t] = String(s).split(' '); return formatDate(d) + (t ? ' · ' + formatTime(t.substring(0, 5)) : ''); };
  const tagCls = a => /delete|purge|reject/.test(a) ? 'del' : /restore|approve/.test(a) ? 'ok' : '';
  let LIMIT = 100;

  async function loadLog() {
    const tb = document.getElementById('actBody');
    const res = await apiCall('get_activity', { q: document.getElementById('actSearch').value.trim(), limit: LIMIT });
    if (!res.success) { tb.innerHTML = `<tr><td colspan="5" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
    tb.innerHTML = res.data.length ? res.data.map(r => `<tr>
      <td>${when(r.created_at)}</td>
      <td>${escapeHtml(r.user_name || '—')}<br><small style="color:var(--text-soft)">${r.role === 'secretariat' ? 'Secretariat' : r.role ? 'Slot Leader' : ''}</small></td>
      <td><span class="act-tag ${tagCls(r.action)}">${escapeHtml(r.action.replace(/_/g, ' '))}</span></td>
      <td>${escapeHtml(r.target || '—')}</td>
      <td class="act-det">${escapeHtml(r.details || '')}</td></tr>`).join('') : '<tr><td colspan="5" class="empty-msg">No activity recorded yet.</td></tr>';
    document.getElementById('actMore').classList.toggle('hidden', res.data.length < LIMIT);
  }

  async function loadTrash() {
    const tb = document.getElementById('trashBody');
    const res = await apiCall('get_trash');
    if (!res.success) { tb.innerHTML = `<tr><td colspan="5" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
    tb.innerHTML = res.data.length ? res.data.map(r => {
      const left = Math.max(0, 30 - Math.floor((Date.now() - new Date(String(r.deleted_at).replace(' ', 'T')).getTime()) / 86400000));
      return `<tr>
      <td><b>${escapeHtml(r.label)}</b></td>
      <td><span class="status-pill ${r.kind === 'user' ? 'role-secretariat' : 'active'}">${r.kind === 'user' ? 'User' : 'Youth member'}</span></td>
      <td>${escapeHtml(r.deleted_by || '—')}<br><small style="color:var(--text-soft)">${when(r.deleted_at)}</small></td>
      <td>${left} day(s)</td>
      <td>
        <button class="table-btn approve-btn" data-act="restore" data-id="${r.id}" title="Restore" style="color:var(--emerald)"><i class="fas fa-rotate-left"></i></button>
        <button class="table-btn delete-btn" data-act="purge" data-id="${r.id}" data-name="${escapeHtml(r.label)}" title="Delete forever"><i class="fas fa-trash"></i></button>
      </td></tr>`;
    }).join('') : '<tr><td colspan="5" class="empty-msg">The Recycle Bin is empty.</td></tr>';
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const page = document.getElementById('page-admin');
    if (!page || typeof apiCall !== 'function') return;
    const s = await apiCall('check_session');
    if (!(s.success && s.data.role === 'secretariat')) return;
    const tabs = page.querySelector('.admin-tabs-wrap');

    const mk = (key, icon, label, html, onOpen) => {
      const btn = document.createElement('button');
      btn.className = 'admin-tab'; btn.dataset.adminTab = key;
      btn.innerHTML = `<i class="fas ${icon}"></i> ${label}`;
      tabs.appendChild(btn);
      const sec = document.createElement('div');
      sec.className = 'admin-section'; sec.id = key; sec.innerHTML = html;
      page.appendChild(sec);
      btn.addEventListener('click', () => {
        page.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
        page.querySelectorAll('.admin-section').forEach(x => x.classList.remove('active'));
        btn.classList.add('active'); sec.classList.add('active'); onOpen();
      });
      return sec;
    };

    mk('admin-activity', 'fa-clock-rotate-left', 'Activity Log', `
      <div class="card"><div class="card-header"><div><h3>Activity Log</h3>
        <p class="card-sub">Who marked or changed attendance, accounts and deletions (kept for 6 months)</p></div></div>
        <div class="act-bar"><input type="text" id="actSearch" placeholder="Search person, action, name, date..." autocomplete="off"/>
          <button class="cancel-btn" id="actRefresh"><i class="fas fa-rotate"></i> Refresh</button></div>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
          <tbody id="actBody"><tr><td colspan="5" class="empty-msg">Loading...</td></tr></tbody></table></div>
        <div class="form-actions" style="margin-top:12px"><button class="cancel-btn hidden" id="actMore">Load more</button></div>
      </div>`, () => { LIMIT = 100; loadLog(); });

    mk('admin-trash', 'fa-trash-can-arrow-up', 'Recycle Bin', `
      <div class="card"><div class="card-header"><div><h3>Recycle Bin</h3>
        <p class="card-sub">Deleted users and youth members can be restored for 30 days, with all their attendance</p></div></div>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Name</th><th>Type</th><th>Deleted</th><th>Expires in</th><th>Actions</th></tr></thead>
          <tbody id="trashBody"><tr><td colspan="5" class="empty-msg">Loading...</td></tr></tbody></table></div>
      </div>`, loadTrash);

    let t = null;
    document.getElementById('actSearch').addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { LIMIT = 100; loadLog(); }, 350); });
    document.getElementById('actRefresh').addEventListener('click', loadLog);
    document.getElementById('actMore').addEventListener('click', () => { LIMIT += 100; loadLog(); });
    document.getElementById('trashBody').addEventListener('click', async e => {
      const b = e.target.closest('[data-act]');
      if (!b) return;
      if (b.dataset.act === 'restore') {
        const res = await apiCall('restore_trash', { id: b.dataset.id });
        alert(res.message); if (res.success) loadTrash();
      } else {
        if (!askTypeName(b.dataset.name, 'this item (it will be deleted FOREVER, not just moved)')) return;
        const res = await apiCall('purge_trash', { id: b.dataset.id });
        alert(res.message); if (res.success) loadTrash();
      }
    });
  });
})();
