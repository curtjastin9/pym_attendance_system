/* ============================================================
   PYM ATTENDANCE SYSTEM - FRONTEND LOGIC
   ============================================================ */

const API = 'api.php';

async function apiCall(action, payload = null) {
  try {
    const res = await fetch(`${API}?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    });
    return await res.json();
  } catch (err) {
    return { success: false, message: 'Network error. Please check your connection.' };
  }
}

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr + 'T00:00:00');
  if (isNaN(d)) return dateStr;
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatTime(timeStr) {
  if (!timeStr) return '—';
  const [h, m] = timeStr.split(':');
  const hour = parseInt(h, 10);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const displayHour = hour % 12 === 0 ? 12 : hour % 12;
  return `${displayHour}:${m} ${ampm}`;
}

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('loginForm')) initAuthPage();
  if (document.getElementById('sidebar')) initDashboard();
});

/* ============================================================
   LOGIN / REGISTER
   ============================================================ */
function initAuthPage() {
  const tabs = document.querySelectorAll('.auth-tab');
  const forms = document.querySelectorAll('.auth-form');
  const messageBox = document.getElementById('authMessage');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      forms.forEach(f => f.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.tab + 'Form').classList.add('active');
      messageBox.textContent = '';
      messageBox.className = 'auth-message';
    });
  });

  function showMessage(msg, isError) {
    messageBox.textContent = msg;
    messageBox.className = 'auth-message ' + (isError ? 'error' : 'success');
  }

  const loginForm = document.getElementById('loginForm');
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = loginForm.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
    const res = await apiCall('login', { username: loginForm.username.value.trim(), password: loginForm.password.value });
    btn.disabled = false;
    btn.innerHTML = original;
    if (res.success) {
      showMessage(res.message, false);
      setTimeout(() => { window.location.href = 'index.php'; }, 600);
    } else {
      showMessage(res.message || 'Login failed.', true);
    }
  });

  const registerForm = document.getElementById('registerForm');
  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = registerForm.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
    const res = await apiCall('register', {
      full_name: registerForm.full_name.value.trim(),
      username: registerForm.username.value.trim(),
      password: registerForm.password.value,
      role: registerForm.role.value
    });
    btn.disabled = false;
    btn.innerHTML = original;
    if (res.success) {
      showMessage(res.message, false);
      registerForm.reset();
      setTimeout(() => {
        document.querySelector('.auth-tab[data-tab="login"]').click();
        showMessage('Account created! Please wait for Secretariat approval before logging in.', false);
      }, 1500);
    } else {
      showMessage(res.message || 'Registration failed.', true);
    }
  });
}

/* ============================================================
   DASHBOARD APP
   ============================================================ */
let CURRENT_USER = null;
let ALL_SLOTS = [];
let ALL_EVENTS = [];

function initDashboard() {
  loadSession();
  initSidebarToggle();
  initNav();
  initLogout();
  initYouthPage();
  initInsertPage();
  initEventsPage();
  initSlotsPage();
  initAttendancePage();
  initUsersPage();
  initAdminPanel();
  initCategories();
  initEventAttendance();
  loadDashboardStats();
}

async function loadSession() {
  const res = await apiCall('check_session');
  if (!res.success) { window.location.href = 'login.php'; return; }
  CURRENT_USER = res.data;

  document.getElementById('sidebarUserName').textContent = CURRENT_USER.full_name;
  document.getElementById('sidebarUserRole').textContent = CURRENT_USER.role === 'secretariat' ? 'Secretariat' : 'Slot Leader';

  document.getElementById('settingsFullName').textContent = CURRENT_USER.full_name;
  document.getElementById('settingsUsername').textContent = CURRENT_USER.username;
  document.getElementById('settingsRole').textContent = CURRENT_USER.role === 'secretariat' ? 'Secretariat' : 'Slot Leader';

  if (CURRENT_USER.role !== 'secretariat') {
    document.querySelectorAll('.nav-item.secretariat-only, .nav-section.secretariat-only').forEach(el => el.style.display = 'none');
    const evActH = document.getElementById('eventsActionHeader');
    if (evActH) evActH.style.display = 'none';
    const slActH = document.getElementById('slotsActionHeader');
    if (slActH) slActH.style.display = 'none';
  } else {
    const showEvBtn = document.getElementById('showEventFormBtn');
    if (showEvBtn) showEvBtn.style.display = '';
    const showSlBtn = document.getElementById('showSlotFormBtn');
    if (showSlBtn) showSlBtn.style.display = '';
  }
}

function initSidebarToggle() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const hamburger = document.getElementById('hamburger');
  hamburger.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('open'); });
  overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('open'); });
}

function initNav() {
  const navItems = document.querySelectorAll('.nav-item');
  const pages = document.querySelectorAll('.page');
  const pageTitle = document.querySelector('.page-title');
  const pageSubtitle = document.querySelector('.page-subtitle');

  const titles = {
    dashboard:  ['Dashboard', 'Secretary & Leaders can mark attendance'],
    youth:      ['Youth Members', 'Manage registered parish youth'],
    attendance: ['Attendance', "Select an event then mark who's present"],
    events:     ['Events', 'Parish events and scheduled masses'],
    groups:     ['Groups / Slots', 'Mass slot groups and their leaders'],
    admin:      ['Admin Panel', 'Manage events and group slots'],
    users:      ['Users & Roles', 'Manage system users and permissions'],
    settings:   ['Settings', 'Your current session'],
    insert:     ['Insert Members', 'Paste member list from Excel to bulk add']
  };

  function goToPage(key) {
    navItems.forEach(n => n.classList.remove('active'));
    const navItem = document.querySelector(`.nav-item[data-page="${key}"]`);
    if (navItem) navItem.classList.add('active');
    pages.forEach(p => p.classList.remove('active'));
    const page = document.getElementById('page-' + key);
    if (page) page.classList.add('active');
    if (titles[key]) { pageTitle.textContent = titles[key][0]; pageSubtitle.textContent = titles[key][1]; }
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('open');
    if (key === 'youth') loadYouth();
    if (key === 'events') loadEvents();
    if (key === 'groups') loadSlots();
    if (key === 'attendance') { loadMySlot().then(() => loadCalendar()); loadAttendanceCategories(); }
    if (key === 'users') loadUsers();
    if (key === 'dashboard') loadDashboardStats();
    if (key === 'admin') loadAdminPage();
  }

  navItems.forEach(item => {
    item.addEventListener('click', (e) => { e.preventDefault(); goToPage(item.dataset.page); });
  });
  document.querySelectorAll('[data-page-link]').forEach(el => {
    el.addEventListener('click', (e) => { e.preventDefault(); goToPage(el.dataset.pageLink); });
  });
  window.goToPage = goToPage;
}

function initLogout() {
  async function doLogout(e) {
    e.preventDefault();
    await apiCall('logout');
    window.location.href = 'home.php';
  }
  document.getElementById('logoutBtn').addEventListener('click', doLogout);
  const settingsLogout = document.getElementById('settingsLogoutBtn');
  if (settingsLogout) settingsLogout.addEventListener('click', doLogout);
}

/* ============================================================
   DASHBOARD STATS
   ============================================================ */
async function loadDashboardStats() {
  const res = await apiCall('get_dashboard_stats');
  if (!res.success) return;
  const d = res.data;

  document.getElementById('statTotalYouth').textContent = d.total_youth;
  document.getElementById('statPresentToday').textContent = d.present_today;
  document.getElementById('statAbsentToday').textContent = d.absent_today;
  document.getElementById('statUpcomingEvents').textContent = d.upcoming_events_count;
  document.getElementById('todayDate').textContent = d.today_date;

  const servicesList = document.getElementById('servicesList');
  if (d.today_services.length === 0) {
    servicesList.innerHTML = '<p class="empty-msg">No events scheduled for today.</p>';
  } else {
    servicesList.innerHTML = d.today_services.map(s => `
      <div class="service-row">
        <div class="service-time"><i class="fas fa-clock"></i> ${escapeHtml(s.title)} ${s.time ? '· ' + formatTime(s.time) : ''}</div>
        <div class="service-stats"><span class="present-count">${s.present}</span><span class="sep">/</span><span class="total-count">${s.total}</span></div>
        <button class="view-btn" data-page-link="attendance">View</button>
      </div>
    `).join('');
    servicesList.querySelectorAll('[data-page-link]').forEach(b => {
      b.addEventListener('click', () => window.goToPage('attendance'));
    });
  }

  const recentList = document.getElementById('recentList');
  if (d.recent_attendance.length === 0) {
    recentList.innerHTML = '<p class="empty-msg">No attendance records yet.</p>';
  } else {
    recentList.innerHTML = d.recent_attendance.map(r => `
      <div class="recent-row">
        <div class="status-dot ${r.status}"></div>
        <div class="mini-avatar"><i class="fas fa-user"></i></div>
        <div class="recent-info">
          <div class="r-name">${escapeHtml(r.full_name)}</div>
          <div class="r-group">${escapeHtml(r.slot_name || 'No group')}</div>
        </div>
        <div class="recent-meta">
          <span class="r-time">${r.time_in ? formatTime(r.time_in) : '—'}</span>
          <span class="r-event">${escapeHtml(r.event_title)}</span>
        </div>
      </div>
    `).join('');
  }

  const chartBars = document.getElementById('chartBars');
  const maxVal = Math.max(1, ...d.weekly_chart.map(c => c.count));
  chartBars.innerHTML = d.weekly_chart.map(c => {
    const heightPct = Math.max(4, (c.count / maxVal) * 100);
    return `<div class="bar-wrap"><div class="bar-col"><div class="bar" style="height:${heightPct}%"><span>${c.count}</span></div></div><div class="bar-label">${c.label}</div></div>`;
  }).join('');

  const eventTypeColors = { mass: 'blue', youth: 'gold', bible: 'purple', recollect: 'green' };
  const dashboardEventList = document.getElementById('dashboardEventList');
  if (d.upcoming_events.length === 0) {
    dashboardEventList.innerHTML = '<p class="empty-msg">No upcoming events.</p>';
  } else {
    dashboardEventList.innerHTML = d.upcoming_events.map(ev => {
      const dt = new Date(ev.event_date + 'T00:00:00');
      const day = dt.getDate();
      const month = dt.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
      const color = eventTypeColors[ev.type] || 'blue';
      return `
        <div class="event-item">
          <div class="event-date-block ${color}"><span class="e-day">${day}</span><span class="e-month">${month}</span></div>
          <div class="event-details">
            <div class="e-title">${escapeHtml(ev.title)}</div>
            <div class="e-meta"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(ev.location || 'No location set')}</div>
          </div>
          <span class="event-tag ${escapeHtml(ev.type)}">${escapeHtml(ev.type)}</span>
        </div>
      `;
    }).join('');
  }

  const slotList = document.getElementById('slotList');
  if (d.slot_summary.length === 0) {
    slotList.innerHTML = '<p class="empty-msg">No groups/slots created yet.</p>';
  } else {
    slotList.innerHTML = d.slot_summary.map(s => {
      let cls = 'blue';
      if (s.pct >= 80) cls = 'green';
      else if (s.pct >= 50) cls = 'gold';
      else if (s.pct > 0) cls = 'red';
      return `
        <div class="slot-row">
          <div class="slot-name"><span class="slot-dot" style="background:${escapeHtml(s.color)}"></span>${escapeHtml(s.name)}</div>
          <div class="slot-bar-wrap"><div class="slot-progress ${cls}" style="width:${s.pct}%"></div></div>
          <div class="slot-pct">${s.pct}%</div>
        </div>
      `;
    }).join('');
  }
}

/* ============================================================
   YOUTH MEMBERS
   ============================================================ */
function initYouthPage() {
  const showBtn = document.getElementById('showYouthFormBtn');
  const form = document.getElementById('youthForm');
  const cancelBtn = document.getElementById('cancelYouthFormBtn');

  showBtn.addEventListener('click', () => { form.reset(); form.id.value = ''; form.classList.remove('hidden'); });
  cancelBtn.addEventListener('click', () => { form.classList.add('hidden'); form.reset(); });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
      id: form.id.value,
      full_name: form.full_name.value.trim(),
      slot_id: '',
      status: form.status.value
    };
    const res = await apiCall('save_youth', data);
    alert(res.message);
    if (res.success) { form.reset(); form.classList.add('hidden'); loadYouth(); }
  });
}

async function populateSlotSelect(selectEl, includeNone = true) {
  if (ALL_SLOTS.length === 0) {
    const res = await apiCall('get_slots');
    if (res.success) ALL_SLOTS = res.data;
  }
  selectEl.innerHTML = (includeNone ? '<option value="">— None —</option>' : '') +
    ALL_SLOTS.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
}

async function loadYouth() {
  const res = await apiCall('get_youth');
  const tbody = document.getElementById('youthTableBody');
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="4" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
  if (res.data.length === 0) { tbody.innerHTML = '<tr><td colspan="4" class="empty-msg">No youth members yet.</td></tr>'; return; }
  tbody.innerHTML = res.data.map(m => `
    <tr>
      <td>${escapeHtml(m.full_name)}</td>
      <td>${m.slot_name ? `<span class="slot-dot" style="display:inline-block;background:${escapeHtml(m.slot_color)}"></span> ${escapeHtml(m.slot_name)}` : '<span style="color:var(--text-light)">— None —</span>'}</td>
      <td><span class="status-pill ${m.status}">${m.status}</span></td>
      <td>
        <button class="table-btn edit-btn" data-id="${m.id}" title="Edit"><i class="fas fa-pen"></i></button>
        <button class="table-btn delete-btn" data-id="${m.id}" title="Delete"><i class="fas fa-trash"></i></button>
      </td>
    </tr>
  `).join('');
  tbody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => editYouth(btn.dataset.id, res.data)));
  tbody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => deleteYouth(btn.dataset.id)));
}

function editYouth(id, list) {
  const m = list.find(x => String(x.id) === String(id));
  if (!m) return;
  const form = document.getElementById('youthForm');
  form.id.value = m.id;
  form.full_name.value = m.full_name;
  form.status.value = m.status;
  form.classList.remove('hidden');
  form.scrollIntoView({ behavior: 'smooth' });
}

async function deleteYouth(id) {
  if (!confirm('Delete this member? Their attendance records will also be removed.')) return;
  const res = await apiCall('delete_youth', { id });
  alert(res.message);
  if (res.success) loadYouth();
}


/* ============================================================
   INSERT MEMBERS (Excel Paste)
   ============================================================ */
function initInsertPage() {
  const textarea  = document.getElementById('insertExcelText');
  const previewBtn= document.getElementById('insertPreviewBtn');
  const clearBtn  = document.getElementById('insertClearBtn');
  const previewSection = document.getElementById('insertPreviewSection');
  const tbody     = document.getElementById('insertPreviewBody');
  const saveBtn   = document.getElementById('insertSaveBtn');
  const msgBox    = document.getElementById('insertMessage');

  if (!textarea) return; // page not in DOM yet

  let parsedRows = [];

  function showMsg(msg, isError) {
    msgBox.textContent = msg;
    msgBox.className = 'insert-msg ' + (isError ? 'error' : 'success');
    setTimeout(() => { msgBox.textContent = ''; msgBox.className = 'insert-msg'; }, 4000);
  }

  previewBtn.addEventListener('click', () => {
    const raw = textarea.value.trim();
    if (!raw) { showMsg('Please paste some data first.', true); return; }

    parsedRows = [];
    const lines = raw.split('\n').filter(l => l.trim() !== '');
    lines.forEach((line, i) => {
      // Split by tab (Excel default) or multiple spaces
      const cols = line.split(/\t/).map(c => c.trim());
      const full_name = cols[0] || '';
      const status    = (cols[1] || 'active').toLowerCase();
      const validStatus = ['active', 'inactive'].includes(status) ? status : 'active';
      if (full_name) parsedRows.push({ full_name, status: validStatus });
    });

    if (parsedRows.length === 0) { showMsg('No valid rows found. Make sure Column 1 = Full Name.', true); return; }

    tbody.innerHTML = parsedRows.map((r, i) => `
      <tr>
        <td style="color:var(--text-light);font-size:12px;">${i + 1}</td>
        <td><strong>${escapeHtml(r.full_name)}</strong></td>
        <td><span class="status-pill ${r.status}">${r.status}</span></td>
        <td style="color:var(--text-light);font-size:11px;">— None —</td>
      </tr>
    `).join('');

    previewSection.classList.remove('hidden');
    document.getElementById('insertCountBadge').textContent = parsedRows.length + ' member(s) ready to import';
    previewSection.scrollIntoView({ behavior: 'smooth' });
  });

  clearBtn.addEventListener('click', () => {
    textarea.value = '';
    parsedRows = [];
    previewSection.classList.add('hidden');
    msgBox.textContent = '';
  });

  saveBtn.addEventListener('click', async () => {
    if (parsedRows.length === 0) { showMsg('Nothing to save. Preview first.', true); return; }
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    let successCount = 0, failCount = 0;
    for (const r of parsedRows) {
      const res = await apiCall('save_youth', { id: '', full_name: r.full_name, slot_id: '', status: r.status });
      if (res.success) successCount++; else failCount++;
    }

    saveBtn.disabled = false;
    saveBtn.innerHTML = '<i class="fas fa-file-import"></i> Import Members';
    textarea.value = '';
    parsedRows = [];
    previewSection.classList.add('hidden');
    showMsg(`Done! ${successCount} member(s) imported successfully.${failCount ? ' ' + failCount + ' failed.' : ''}`, failCount > 0);
    // Refresh youth list if it was loaded
    loadYouth();
  });
}

/* ============================================================
   EVENTS PAGE
   ============================================================ */
function initEventsPage() {
  const showBtn = document.getElementById('showEventFormBtn');
  const form = document.getElementById('eventForm');
  const cancelBtn = document.getElementById('cancelEventFormBtn');
  if (showBtn) showBtn.addEventListener('click', () => { form.reset(); form.id.value = ''; form.classList.remove('hidden'); });
  if (cancelBtn) cancelBtn.addEventListener('click', () => { form.classList.add('hidden'); form.reset(); });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
      id: form.id.value, title: form.title.value.trim(), type: form.type.value,
      event_date: form.event_date.value, event_time: form.event_time.value, location: form.location.value.trim()
    };
    const res = await apiCall('save_event', data);
    alert(res.message);
    if (res.success) { form.reset(); form.classList.add('hidden'); loadEvents(); }
  });
}

async function loadEvents(isAdmin = false) {
  const res = await apiCall('get_events');
  const bodyId = isAdmin ? 'adminEventsTableBody' : 'eventsTableBody';
  const tbody = document.getElementById(bodyId);
  if (!tbody) return;
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="6" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
  ALL_EVENTS = res.data;
  if (res.data.length === 0) { tbody.innerHTML = '<tr><td colspan="6" class="empty-msg">No events yet.</td></tr>'; return; }

  const isSecretariat = CURRENT_USER && CURRENT_USER.role === 'secretariat';
  tbody.innerHTML = res.data.map(ev => `
    <tr>
      <td>${escapeHtml(ev.title)}</td>
      <td>${formatDate(ev.event_date)}</td>
      <td>${ev.event_time ? formatTime(ev.event_time) : '—'}</td>
      <td>${escapeHtml(ev.location || '—')}</td>
      <td><span class="event-tag ${escapeHtml(ev.type)}">${escapeHtml(ev.type)}</span></td>
      ${(isAdmin || isSecretariat) ? `<td>
        <button class="table-btn edit-btn" data-id="${ev.id}" title="Edit"><i class="fas fa-pen"></i></button>
        <button class="table-btn delete-btn" data-id="${ev.id}" title="Delete"><i class="fas fa-trash"></i></button>
      </td>` : '<td>—</td>'}
    </tr>
  `).join('');
  tbody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => editEvent(btn.dataset.id, isAdmin)));
  tbody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => deleteEvent(btn.dataset.id, isAdmin)));
}

function editEvent(id, isAdmin = false) {
  const ev = ALL_EVENTS.find(x => String(x.id) === String(id));
  if (!ev) return;
  const formId = isAdmin ? 'adminEventForm' : 'eventForm';
  const form = document.getElementById(formId);
  form.id.value = ev.id; form.title.value = ev.title; form.type.value = ev.type;
  form.event_date.value = ev.event_date; form.event_time.value = ev.event_time || '';
  form.location.value = ev.location || '';
  form.classList.remove('hidden'); form.scrollIntoView({ behavior: 'smooth' });
}

async function deleteEvent(id, isAdmin = false) {
  if (!confirm('Delete this event?')) return;
  const res = await apiCall('delete_event', { id });
  alert(res.message);
  if (res.success) loadEvents(isAdmin);
}

/* ============================================================
   GROUPS / SLOTS PAGE
   ============================================================ */
let ALL_SLOTS_FULL = [];

function initSlotsPage() {
  const form = document.getElementById('slotForm');
  const cancelBtn = document.getElementById('cancelSlotFormBtn');
  if (cancelBtn) cancelBtn.addEventListener('click', () => { form.classList.add('hidden'); form.reset(); });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = { id: form.id.value, color: form.color.value,
                   leader_ids: getCheckedLeaders(document.getElementById('slotLeaderSelect')) };
    const res = await apiCall('save_slot', data);
    alert(res.message);
    if (res.success) { form.reset(); form.classList.add('hidden'); ALL_SLOTS = []; loadSlots(); }
  });
}

const MAX_LEADERS_PER_SLOT = 5;

async function populateLeaderSelect(container, selectedIds = [], currentSlotId = null) {
  const res = await apiCall('get_leaders');
  const users = res.success ? res.data : [];
  const selected = selectedIds.map(String);

  container.innerHTML = users.length ? users.map(u => {
    const takenElsewhere = u.assigned_slot_id && String(u.assigned_slot_id) !== String(currentSlotId);
    return `
      <label class="leader-check ${takenElsewhere ? 'taken' : ''}">
        <input type="checkbox" value="${u.id}" ${selected.includes(String(u.id)) ? 'checked' : ''} ${takenElsewhere ? 'disabled' : ''}/>
        <span>${escapeHtml(u.full_name)}</span>
        <small>${u.role === 'secretariat' ? 'Secretariat/Admin' : 'Leader'}${u.position_name ? ' · ' + escapeHtml(u.position_name) : ''}</small>
        ${takenElsewhere ? `<small>(${escapeHtml(u.assigned_slot_name)})</small>` : ''}
      </label>`;
  }).join('') : '<p class="empty-msg">No approved users.</p>';

  container.onchange = (e) => {
    if (container.querySelectorAll('input:checked').length > MAX_LEADERS_PER_SLOT) {
      e.target.checked = false;
      alert(`Maximum of ${MAX_LEADERS_PER_SLOT} leaders per slot.`);
    }
  };
}

function getCheckedLeaders(container) {
  return Array.from(container.querySelectorAll('input:checked')).map(i => i.value);
}

async function loadSlots(isAdmin = false) {
  const res = await apiCall('get_slots');
  const bodyId = isAdmin ? 'adminSlotsTableBody' : 'slotsTableBody';
  const tbody = document.getElementById(bodyId);
  if (!tbody) return;
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="4" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
  ALL_SLOTS_FULL = res.data; ALL_SLOTS = res.data;
  if (res.data.length === 0) { tbody.innerHTML = '<tr><td colspan="4" class="empty-msg">No groups/slots yet.</td></tr>'; return; }

  const isSecretariat = CURRENT_USER && CURRENT_USER.role === 'secretariat';
  tbody.innerHTML = res.data.map(s => `
    <tr>
      <td><span class="slot-dot" style="display:inline-block;background:${escapeHtml(s.color)}"></span> ${escapeHtml(s.name)}</td>
      <td>
        ${s.leaders.length
          ? s.leaders.map(l => `<span class="status-pill role-leader" style="margin:2px 4px 2px 0">${escapeHtml(l.full_name)}</span>`).join('')
          : '—'}
        <small style="color:var(--text-soft)">${s.leaders.length}/${MAX_LEADERS_PER_SLOT}</small>
      </td>
      <td>${s.member_count}</td>
      ${(isAdmin || isSecretariat) ? `<td><button class="table-btn edit-btn" data-id="${s.id}" title="Edit"><i class="fas fa-pen"></i></button></td>` : '<td>—</td>'}
    </tr>
  `).join('');
  tbody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => editSlot(btn.dataset.id, isAdmin)));
}

async function editSlot(id, isAdmin = false) {
  const s = ALL_SLOTS_FULL.find(x => String(x.id) === String(id));
  if (!s) return;
  const form = document.getElementById(isAdmin ? 'adminSlotForm' : 'slotForm');
  const box  = document.getElementById(isAdmin ? 'adminSlotLeaderSelect' : 'slotLeaderSelect');
  await populateLeaderSelect(box, s.leaders.map(l => l.id), s.id);
  form.id.value = s.id; form.name.value = s.name;
  form.color.value = s.color || '#4F8EF7';
  form.classList.remove('hidden'); form.scrollIntoView({ behavior: 'smooth' });
}

/* ============================================================
   ATTENDANCE — CALENDAR-BASED MONTHLY VIEW
   ============================================================ */
let calYear  = new Date().getFullYear();
let calMonth = new Date().getMonth() + 1;
let mySlotId = null;
let mySlotDow = null;  // DOW (0=Sun,6=Sat) of the leader's assigned slot
let CURRENT_ATT_DATE = null;

function initAttendancePage() {
  document.getElementById('calPrevBtn').addEventListener('click', () => {
    calMonth--; if (calMonth < 1) { calMonth = 12; calYear--; } loadCalendar();
  });
  document.getElementById('calNextBtn').addEventListener('click', () => {
    calMonth++; if (calMonth > 12) { calMonth = 1; calYear++; } loadCalendar();
  });

  const select = document.getElementById('attendanceEventSelect');
  const saveBtn = document.getElementById('saveAttendanceBtn');

  select.addEventListener('change', () => {
    if (select.value) loadAttendanceTable(select.value, CURRENT_ATT_DATE);
    else document.getElementById('attendanceTableBody').innerHTML = '<tr><td colspan="4" class="empty-msg">Select a service time above.</td></tr>';
  });

  document.getElementById('closeAttPanel').addEventListener('click', () => {
    document.getElementById('attendancePanel').classList.add('hidden');
  });

  document.getElementById('markAllPresentBtn').addEventListener('click', () => {
    document.querySelectorAll('#attendanceTableBody tr[data-member-id]').forEach(row => {
      const btn = row.querySelector('.status-toggle');
      const ti  = row.querySelector('input[type="time"]');
      if (btn.dataset.status !== 'present') {
        btn.dataset.status = 'present'; btn.textContent = 'Present';
        btn.className = 'status-toggle present';
        ti.disabled = false;
        if (!ti.value) ti.value = new Date().toTimeString().substring(0,5);
      }
    });
  });

  document.getElementById('markAllAbsentBtn').addEventListener('click', () => {
    document.querySelectorAll('#attendanceTableBody tr[data-member-id]').forEach(row => {
      const btn = row.querySelector('.status-toggle');
      const ti  = row.querySelector('input[type="time"]');
      btn.dataset.status = 'absent'; btn.textContent = 'Absent';
      btn.className = 'status-toggle absent';
      ti.value = ''; ti.disabled = true;
    });
  });

  saveBtn.addEventListener('click', async () => {
    const slotId = select.value;
    const date = CURRENT_ATT_DATE;
    if (!slotId) { alert('Please select a service time first.'); return; }
    if (!date) { alert('No date selected.'); return; }
    const rows = document.querySelectorAll('#attendanceTableBody tr[data-member-id]');
    const records = [];
    rows.forEach(row => {
      const statusBtn = row.querySelector('.status-toggle');
      const timeInput = row.querySelector('input[type="time"]');
      records.push({ member_id: row.dataset.memberId, status: statusBtn.dataset.status, time_in: timeInput ? timeInput.value : null });
    });
    saveBtn.disabled = true;
    const res = await apiCall('save_attendance', { slot_id: slotId, date, records });
    saveBtn.disabled = false;
    alert(res.message);
    if (res.success) { loadCalendar(); loadDashboardStats(); }
  });
}

async function loadMySlot() {
  if (!CURRENT_USER) return;
  if (CURRENT_USER.role === 'leader') {
    const res = await apiCall('get_my_slot');
    const banner = document.getElementById('leaderSlotBanner');
    if (res.success && res.data && res.data.slot_id) {
      mySlotId = res.data.slot_id;
      mySlotDow = (res.data.dow !== null && res.data.dow !== undefined) ? parseInt(res.data.dow) : null;
      document.getElementById('leaderSlotName').textContent = res.data.slot_name || '—';
      banner.classList.remove('hidden');
    } else {
      banner.classList.add('hidden');
      mySlotId = null;
      mySlotDow = null;
    }
  }
}

async function loadCalendar() {
  const grid = document.getElementById('calGrid');
  const display = document.getElementById('calMonthDisplay');
  grid.innerHTML = '<div class="cal-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

  const res = await apiCall('get_monthly_calendar', { year: calYear, month: calMonth });
  if (!res.success) { grid.innerHTML = `<div class="cal-loading">${escapeHtml(res.message)}</div>`; return; }

  const d = res.data;
  display.textContent = d.month_name;
  document.getElementById('calMonthLabel').textContent = CURRENT_USER && CURRENT_USER.role === 'leader'
    ? 'Normal Serve · Your assigned slot schedule'
    : 'Normal Serve · Sat 6PM · Sun 7AM, 8:30AM, 10AM, 5PM, 7PM';

  grid.innerHTML = '';
  d.calendar.forEach(day => {
    if (day === null) {
      const blank = document.createElement('div');
      blank.className = 'cal-cell cal-blank';
      grid.appendChild(blank);
      return;
    }

    const cell = document.createElement('div');
    const isToday = day.is_today;
    const isWeekend = day.is_weekend;
    cell.className = 'cal-cell' +
      (isWeekend ? ' cal-weekend' : '') +
      (isToday   ? ' cal-today'   : '') +
      (!isWeekend ? ' cal-weekday' : '');

    const dowName = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][day.dow];

    // A day is "active" for a user when:
    //   - backend returned slot events for it (always true for secretariat, filtered for leaders), OR
    //   - this is a leader whose assigned slot DOW matches this calendar cell's DOW
    const hasEventsForUser = day.events.length > 0;
    const isLeaderDay = CURRENT_USER.role === 'leader' && mySlotDow !== null && day.dow === mySlotDow;
    const isActiveDay = isWeekend && (hasEventsForUser || isLeaderDay);

    let evHtml = '';
    if (hasEventsForUser) {
      evHtml = day.events.map(ev => {
        const pct = ev.total > 0 ? Math.round((ev.present/ev.total)*100) : 0;
        return `<div class="cal-event-dot" data-event-id="${ev.id}" data-date="${day.date}" title="${escapeHtml(ev.title)} — ${ev.present}/${ev.total}">
          <span class="cal-ev-time">${ev.time ? formatTime(ev.time) : ''}</span>
          <span class="cal-ev-pct ${pct>=80?'hi':pct>=50?'mid':'lo'}">${pct}%</span>
        </div>`;
      }).join('');
    } else if (isWeekend) {
      // Only show "No service logged" for secretariat; leaders see nothing on days not their slot
      if (CURRENT_USER.role === 'secretariat') {
        evHtml = `<div class="cal-no-event">No service logged</div>`;
      }
    }

    cell.innerHTML = `
      <div class="cal-day-num ${isToday ? 'today-num' : ''}">${day.day}<span class="cal-dow">${dowName}</span></div>
      <div class="cal-events">${evHtml}</div>
      ${isActiveDay ? `<button class="cal-mark-btn" data-date="${day.date}"><i class="fas fa-plus"></i> Mark</button>` : ''}
    `;

    if (isActiveDay) {
      const markBtn = cell.querySelector('.cal-mark-btn');
      if (markBtn) {
        markBtn.addEventListener('click', (e) => { e.stopPropagation(); openAttendancePanel(day.date); });
      }
      cell.querySelectorAll('.cal-event-dot').forEach(dot => {
        dot.addEventListener('click', () => { openAttendancePanel(day.date, dot.dataset.eventId); });
      });
    }

    grid.appendChild(cell);
  });
}

async function openAttendancePanel(date, preSelectSlotId = null) {
  const panel = document.getElementById('attendancePanel');
  const select = document.getElementById('attendanceEventSelect');
  const tbody  = document.getElementById('attendanceTableBody');

  CURRENT_ATT_DATE = date;
  panel.classList.remove('hidden');
  document.getElementById('attPanelTitle').textContent = 'Mark Attendance';
  document.getElementById('attPanelSub').textContent = formatDate(date);

  tbody.innerHTML = '<tr><td colspan="4" class="empty-msg">Loading...</td></tr>';
  select.innerHTML = '<option value="">— Select Service Time —</option>';

  const res = await apiCall('get_events_for_date', { date });
  if (res.success && res.data.length > 0) {
    ALL_EVENTS = res.data;
    select.innerHTML = '<option value="">— Select Service Time —</option>' +
      res.data.map(ev => `<option value="${ev.id}">${escapeHtml(ev.title)} ${ev.event_time ? '· ' + formatTime(ev.event_time) : ''}</option>`).join('');

    if (preSelectSlotId) {
      select.value = preSelectSlotId;
      loadAttendanceTable(preSelectSlotId, date);
    } else if (res.data.length === 1) {
      select.value = res.data[0].id;
      loadAttendanceTable(res.data[0].id, date);
    } else {
      tbody.innerHTML = '<tr><td colspan="4" class="empty-msg">Select a service time above.</td></tr>';
    }
  } else {
    select.innerHTML = '<option value="">— No slot available —</option>';
    tbody.innerHTML = `<tr><td colspan="4" class="empty-msg">You have no assigned slot scheduled on ${formatDate(date)}.</td></tr>`;
  }

  panel.scrollIntoView({ behavior: 'smooth' });
}

async function loadAttendanceTable(slotId, date) {
  const tbody = document.getElementById('attendanceTableBody');
  tbody.innerHTML = '<tr><td colspan="4" class="empty-msg">Loading...</td></tr>';

  const res = await apiCall('get_attendance_for_slot', { slot_id: slotId, date: date || CURRENT_ATT_DATE });
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="4" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
  if (res.data.length === 0) {
    tbody.innerHTML = CURRENT_USER && CURRENT_USER.role === 'leader' && !mySlotId
      ? '<tr><td colspan="4" class="empty-msg">You have no slot assigned. Ask Secretariat to assign you to a slot.</td></tr>'
      : '<tr><td colspan="4" class="empty-msg">No active youth members found in this slot.</td></tr>';
    return;
  }

  tbody.innerHTML = res.data.map(m => {
    const status = m.status || 'absent';
    const timeVal = m.time_in ? m.time_in.substring(0, 5) : '';
    return `
      <tr data-member-id="${m.member_id}">
        <td>${escapeHtml(m.full_name)}</td>
        <td>${escapeHtml(m.slot_name || '—')}</td>
        <td><button type="button" class="status-toggle ${status}" data-status="${status}">${status === 'present' ? 'Present' : 'Absent'}</button></td>
        <td><input type="time" value="${timeVal}" style="border:1px solid var(--border);border-radius:8px;padding:6px 10px;font-size:12px;" ${status === 'absent' ? 'disabled' : ''}/></td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('.status-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const row = btn.closest('tr');
      const timeInput = row.querySelector('input[type="time"]');
      if (btn.dataset.status === 'present') {
        btn.dataset.status = 'absent'; btn.textContent = 'Absent';
        btn.classList.replace('present', 'absent');
        timeInput.value = ''; timeInput.disabled = true;
      } else {
        btn.dataset.status = 'present'; btn.textContent = 'Present';
        btn.classList.replace('absent', 'present');
        timeInput.disabled = false;
        if (!timeInput.value) timeInput.value = new Date().toTimeString().substring(0, 5);
      }
    });
  });
}

/* ============================================================
   ADMIN PANEL
   ============================================================ */
function initAdminPanel() {
  document.querySelectorAll('.admin-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.adminTab).classList.add('active');
    });
  });

  const adminShowEvBtn = document.getElementById('adminShowEventFormBtn');
  const adminEventForm = document.getElementById('adminEventForm');
  const adminCancelEvBtn = document.getElementById('adminCancelEventFormBtn');
  adminShowEvBtn.addEventListener('click', () => { adminEventForm.reset(); adminEventForm.id.value = ''; adminEventForm.classList.remove('hidden'); });
  adminCancelEvBtn.addEventListener('click', () => { adminEventForm.classList.add('hidden'); adminEventForm.reset(); });
  adminEventForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
      id: adminEventForm.id.value, title: adminEventForm.title.value.trim(),
      type: adminEventForm.type.value, event_date: adminEventForm.event_date.value,
      event_time: adminEventForm.event_time.value, location: adminEventForm.location.value.trim()
    };
    const res = await apiCall('save_event', data);
    alert(res.message);
    if (res.success) { adminEventForm.reset(); adminEventForm.classList.add('hidden'); loadEvents(true); }
  });

  const adminSlotForm = document.getElementById('adminSlotForm');
  const adminCancelSlBtn = document.getElementById('adminCancelSlotFormBtn');
  adminCancelSlBtn.addEventListener('click', () => { adminSlotForm.classList.add('hidden'); adminSlotForm.reset(); });
  adminSlotForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = { id: adminSlotForm.id.value, color: adminSlotForm.color.value,
                   leader_ids: getCheckedLeaders(document.getElementById('adminSlotLeaderSelect')) };
    const res = await apiCall('save_slot', data);
    alert(res.message);
    if (res.success) { adminSlotForm.reset(); adminSlotForm.classList.add('hidden'); ALL_SLOTS = []; loadSlots(true); }
  });
}

async function loadAdminPage() {
  await loadEvents(true);
  await loadSlots(true);
  await loadAdminCategories();
}

/* ============================================================
   USERS & ROLES
   ============================================================ */
let ALL_USERS = [];

function initUsersPage() {
  const showBtn = document.getElementById('showUserFormBtn');
  const form = document.getElementById('userForm');
  const cancelBtn = document.getElementById('cancelUserFormBtn');
  showBtn.addEventListener('click', () => { form.reset(); form.id.value = ''; form.password.required = true; form.classList.remove('hidden'); });
  cancelBtn.addEventListener('click', () => { form.classList.add('hidden'); form.reset(); });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = {
      id: form.id.value, full_name: form.full_name.value.trim(),
      username: form.username.value.trim(), password: form.password.value,
      role: form.role.value, status: form.status.value
    };
    const res = await apiCall('save_user', data);
    alert(res.message);
    if (res.success) { form.reset(); form.classList.add('hidden'); loadUsers(); }
  });
}

async function loadUsers() {
  if (!CURRENT_USER || CURRENT_USER.role !== 'secretariat') return;

  const pendingSection = document.getElementById('pendingUsersSection');
  const pendingBody = document.getElementById('pendingUsersBody');
  const pendingRes = await apiCall('get_pending_users');

  if (pendingRes.success && pendingRes.data.length > 0) {
    pendingSection.classList.remove('hidden');
    pendingBody.innerHTML = pendingRes.data.map(u => `
      <tr>
        <td>${escapeHtml(u.full_name)}</td>
        <td>${escapeHtml(u.username)}</td>
        <td><span class="status-pill role-${escapeHtml(u.role)}">${u.role === 'secretariat' ? 'Secretariat' : 'Slot Leader'}</span></td>
        <td>${formatDate(u.created_at.substring(0, 10))}</td>
        <td>
          <button class="table-btn approve-btn" data-id="${u.id}" style="color:var(--green)" title="Approve"><i class="fas fa-check"></i></button>
          <button class="table-btn reject-btn" data-id="${u.id}" style="color:var(--red)" title="Reject"><i class="fas fa-times"></i></button>
        </td>
      </tr>
    `).join('');
    pendingBody.querySelectorAll('.approve-btn').forEach(btn => btn.addEventListener('click', async () => {
      const res = await apiCall('approve_user', { id: btn.dataset.id });
      alert(res.message); if (res.success) loadUsers();
    }));
    pendingBody.querySelectorAll('.reject-btn').forEach(btn => btn.addEventListener('click', async () => {
      if (!confirm('Reject this account?')) return;
      const res = await apiCall('reject_user', { id: btn.dataset.id });
      alert(res.message); if (res.success) loadUsers();
    }));
  } else {
    pendingSection.classList.add('hidden');
  }

  const res = await apiCall('get_users');
  const tbody = document.getElementById('usersTableBody');
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="6" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
  ALL_USERS = res.data;
  tbody.innerHTML = res.data.map(u => `
    <tr>
      <td>${escapeHtml(u.full_name)}</td>
      <td>${escapeHtml(u.username)}</td>
      <td><span class="status-pill role-${escapeHtml(u.role)}">${u.role === 'secretariat' ? 'Secretariat' : 'Slot Leader'}</span></td>
      <td><span class="status-pill ${escapeHtml(u.status)}">${u.status}</span></td>
      <td>${formatDate(u.created_at.substring(0, 10))}</td>
      <td>
        <button class="table-btn edit-btn" data-id="${u.id}" title="Edit"><i class="fas fa-pen"></i></button>
        <button class="table-btn delete-btn" data-id="${u.id}" title="Delete"><i class="fas fa-trash"></i></button>
      </td>
    </tr>
  `).join('');
  tbody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => editUser(btn.dataset.id)));
  tbody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => deleteUser(btn.dataset.id)));
}

function editUser(id) {
  const u = ALL_USERS.find(x => String(x.id) === String(id));
  if (!u) return;
  const form = document.getElementById('userForm');
  form.id.value = u.id; form.full_name.value = u.full_name;
  form.username.value = u.username; form.password.value = '';
  form.password.required = false; form.role.value = u.role; form.status.value = u.status;
  form.classList.remove('hidden'); form.scrollIntoView({ behavior: 'smooth' });
}

async function deleteUser(id) {
  if (!confirm('Delete this user?')) return;
  const res = await apiCall('delete_user', { id });
  alert(res.message);
  if (res.success) loadUsers();
}


/* ============================================================
   EVENT CATEGORIES (ADMIN) + CATEGORY EVENT ATTENDANCE
   ============================================================ */
let ALL_CATEGORIES = [];
let CAT_EVENT_MAP = {};
let CURRENT_EVENT_ID = null;

function isSecretariatUser() { return CURRENT_USER && CURRENT_USER.role === 'secretariat'; }

function initCategories() {
  const catForm = document.getElementById('categoryForm');
  const evForm  = document.getElementById('categoryEventForm');
  if (!catForm || !evForm) return;
  const el = (f, n) => f.elements[n];

  document.getElementById('showCategoryFormBtn').addEventListener('click', () => {
    catForm.reset(); el(catForm,'id').value = '';
    evForm.classList.add('hidden'); catForm.classList.remove('hidden');
  });
  document.getElementById('cancelCategoryFormBtn').addEventListener('click', () => { catForm.classList.add('hidden'); catForm.reset(); });
  document.getElementById('cancelCategoryEventFormBtn').addEventListener('click', () => { evForm.classList.add('hidden'); evForm.reset(); });

  catForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('save_category', { id: el(catForm,'id').value, name: el(catForm,'name').value.trim() });
    alert(res.message);
    if (res.success) { catForm.reset(); catForm.classList.add('hidden'); loadAdminCategories(); }
  });

  evForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('save_category_event', {
      id: el(evForm,'id').value,
      category_id: el(evForm,'category_id').value,
      title: el(evForm,'title').value.trim(),
      event_date: el(evForm,'event_date').value,
      event_time: el(evForm,'event_time').value,
      location: el(evForm,'location').value.trim()
    });
    alert(res.message);
    if (res.success) { evForm.reset(); evForm.classList.add('hidden'); loadAdminCategories(); }
  });
}

async function loadAdminCategories() {
  const box = document.getElementById('categoriesList');
  if (!box || !isSecretariatUser()) return;
  const res = await apiCall('get_categories');
  if (!res.success) { box.innerHTML = `<p class="empty-msg">${escapeHtml(res.message)}</p>`; return; }
  ALL_CATEGORIES = res.data;

  box.innerHTML = res.data.map(c => {
    const sys = Number(c.is_system) === 1;
    let body;
    if (sys) {
      body = '<p class="cat-note">Fixed weekly slots: Saturday 6:00 PM · Sunday 7:00 AM, 8:30 AM, 10:00 AM, 5:00 PM, 7:00 PM. Assign leaders in Manage Groups / Slots.</p>';
    } else if (!c.events.length) {
      body = '<p class="empty-msg">No events in this category yet.</p>';
    } else {
      body = `<div class="table-wrap"><table class="data-table">
        <thead><tr><th>Title</th><th>Date</th><th>Time</th><th>Location</th><th>Actions</th></tr></thead>
        <tbody>${c.events.map(ev => `
          <tr>
            <td>${escapeHtml(ev.title)}</td>
            <td>${formatDate(ev.event_date)}</td>
            <td>${ev.event_time ? formatTime(ev.event_time) : '—'}</td>
            <td>${escapeHtml(ev.location || '—')}</td>
            <td>
              <button class="table-btn edit-btn cat-ev-edit" data-id="${ev.id}" title="Edit"><i class="fas fa-pen"></i></button>
              <button class="table-btn delete-btn cat-ev-delete" data-id="${ev.id}" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
          </tr>`).join('')}
        </tbody></table></div>`;
    }
    return `
      <div class="cat-block">
        <div class="cat-head">
          <h4><i class="fas fa-tag" style="color:var(--gold-mid)"></i> ${escapeHtml(c.name)} ${sys ? '<span class="cat-badge">Default</span>' : ''}</h4>
          <div class="cat-actions">
            ${sys ? '' : `<button class="add-btn cat-add-event" data-id="${c.id}"><i class="fas fa-plus"></i> Add Event</button>`}
            <button class="table-btn edit-btn cat-rename" data-id="${c.id}" title="Rename"><i class="fas fa-pen"></i></button>
            ${sys ? '' : `<button class="table-btn delete-btn cat-delete" data-id="${c.id}" title="Delete"><i class="fas fa-trash"></i></button>`}
          </div>
        </div>
        ${body}
      </div>`;
  }).join('');

  const catForm = document.getElementById('categoryForm');
  const evForm  = document.getElementById('categoryEventForm');

  box.querySelectorAll('.cat-rename').forEach(b => b.addEventListener('click', () => {
    const c = ALL_CATEGORIES.find(x => String(x.id) === b.dataset.id);
    if (!c) return;
    evForm.classList.add('hidden');
    catForm.elements['id'].value = c.id; catForm.elements['name'].value = c.name;
    catForm.classList.remove('hidden'); catForm.scrollIntoView({ behavior: 'smooth' });
  }));

  box.querySelectorAll('.cat-delete').forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Delete this category and ALL its events and their attendance?')) return;
    const res = await apiCall('delete_category', { id: b.dataset.id });
    alert(res.message); if (res.success) loadAdminCategories();
  }));

  box.querySelectorAll('.cat-add-event').forEach(b => b.addEventListener('click', () => {
    const c = ALL_CATEGORIES.find(x => String(x.id) === b.dataset.id);
    catForm.classList.add('hidden');
    evForm.reset(); evForm.elements['id'].value = ''; evForm.elements['category_id'].value = b.dataset.id;
    document.getElementById('categoryEventFormTitle').textContent = 'Add Event to ' + (c ? c.name : '');
    evForm.classList.remove('hidden'); evForm.scrollIntoView({ behavior: 'smooth' });
  }));

  box.querySelectorAll('.cat-ev-edit').forEach(b => b.addEventListener('click', () => {
    let found = null, cat = null;
    ALL_CATEGORIES.forEach(c => c.events.forEach(ev => { if (String(ev.id) === b.dataset.id) { found = ev; cat = c; } }));
    if (!found) return;
    catForm.classList.add('hidden');
    evForm.elements['id'].value = found.id; evForm.elements['category_id'].value = found.category_id;
    evForm.elements['title'].value = found.title; evForm.elements['event_date'].value = found.event_date;
    evForm.elements['event_time'].value = found.event_time ? found.event_time.substring(0,5) : '';
    evForm.elements['location'].value = found.location || '';
    document.getElementById('categoryEventFormTitle').textContent = 'Edit Event in ' + cat.name;
    evForm.classList.remove('hidden'); evForm.scrollIntoView({ behavior: 'smooth' });
  }));

  box.querySelectorAll('.cat-ev-delete').forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Delete this event and its attendance records?')) return;
    const res = await apiCall('delete_event', { id: b.dataset.id });
    alert(res.message); if (res.success) loadAdminCategories();
  }));
}

/* ---------- Attendance page: category events ---------- */
async function loadAttendanceCategories() {
  const card = document.getElementById('categoryEventsCard');
  const list = document.getElementById('attendanceCategoriesList');
  if (!card || !list) return;
  if (!isSecretariatUser()) { card.classList.add('hidden'); return; }
  const res = await apiCall('get_categories');
  if (!res.success) return;
  const cats = res.data.filter(c => Number(c.is_system) !== 1);
  CAT_EVENT_MAP = {};
  card.classList.remove('hidden');
  if (!cats.length) { list.innerHTML = '<p class="empty-msg">No categories yet. Add them in Admin Panel → Event Categories.</p>'; return; }

  list.innerHTML = cats.map(c => `
    <div class="cat-block">
      <div class="cat-head"><h4><i class="fas fa-tag" style="color:var(--gold-mid)"></i> ${escapeHtml(c.name)}</h4></div>
      ${c.events.length ? c.events.map(ev => {
        CAT_EVENT_MAP[ev.id] = { ev, cat: c.name };
        const dt = new Date(ev.event_date + 'T00:00:00');
        const day = dt.getDate();
        const mon = dt.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
        return `<div class="cat-event-row">
          <div class="event-date-block gold"><span class="e-day">${day}</span><span class="e-month">${mon}</span></div>
          <div class="event-details">
            <div class="e-title">${escapeHtml(ev.title)}</div>
            <div class="e-meta"><i class="fas fa-clock"></i> ${ev.event_time ? formatTime(ev.event_time) : 'No time set'} &nbsp;<i class="fas fa-map-marker-alt"></i> ${escapeHtml(ev.location || 'No location set')}</div>
          </div>
          <button class="cal-mark-btn cat-mark" data-id="${ev.id}" style="width:auto;margin:0;padding:7px 14px"><i class="fas fa-plus"></i> Mark</button>
        </div>`;
      }).join('') : '<p class="empty-msg">No events in this category.</p>'}
    </div>`).join('');

  list.querySelectorAll('.cat-mark').forEach(b => b.addEventListener('click', () => openEventAttendance(b.dataset.id)));
}

function bindAttendanceToggles(tbody) {
  tbody.querySelectorAll('.status-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const ti = btn.closest('tr').querySelector('input[type="time"]');
      if (btn.dataset.status === 'present') {
        btn.dataset.status = 'absent'; btn.textContent = 'Absent';
        btn.classList.replace('present', 'absent');
        ti.value = ''; ti.disabled = true;
      } else {
        btn.dataset.status = 'present'; btn.textContent = 'Present';
        btn.classList.replace('absent', 'present');
        ti.disabled = false;
        if (!ti.value) ti.value = new Date().toTimeString().substring(0, 5);
      }
    });
  });
}

function setAllEventAttendance(present) {
  document.querySelectorAll('#evAttTableBody tr[data-member-id]').forEach(row => {
    const btn = row.querySelector('.status-toggle');
    const ti = row.querySelector('input[type="time"]');
    btn.dataset.status = present ? 'present' : 'absent';
    btn.textContent = present ? 'Present' : 'Absent';
    btn.className = 'status-toggle ' + (present ? 'present' : 'absent');
    if (present) { ti.disabled = false; if (!ti.value) ti.value = new Date().toTimeString().substring(0, 5); }
    else { ti.value = ''; ti.disabled = true; }
  });
}

function initEventAttendance() {
  const panel = document.getElementById('eventAttPanel');
  if (!panel) return;
  document.getElementById('closeEvAttPanel').addEventListener('click', () => panel.classList.add('hidden'));
  document.getElementById('evMarkAllPresentBtn').addEventListener('click', () => setAllEventAttendance(true));
  document.getElementById('evMarkAllAbsentBtn').addEventListener('click', () => setAllEventAttendance(false));
  const saveBtn = document.getElementById('saveEventAttendanceBtn');
  saveBtn.addEventListener('click', async () => {
    if (!CURRENT_EVENT_ID) { alert('No event selected.'); return; }
    const records = [];
    document.querySelectorAll('#evAttTableBody tr[data-member-id]').forEach(row => {
      const b = row.querySelector('.status-toggle');
      const t = row.querySelector('input[type="time"]');
      records.push({ member_id: row.dataset.memberId, status: b.dataset.status, time_in: t ? t.value : null });
    });
    saveBtn.disabled = true;
    const res = await apiCall('save_event_attendance', { event_id: CURRENT_EVENT_ID, records });
    saveBtn.disabled = false;
    alert(res.message);
  });
}

async function openEventAttendance(eventId) {
  const info = CAT_EVENT_MAP[eventId];
  if (!info) return;
  CURRENT_EVENT_ID = eventId;
  const panel = document.getElementById('eventAttPanel');
  const tbody = document.getElementById('evAttTableBody');
  panel.classList.remove('hidden');
  document.getElementById('evAttTitle').textContent = info.ev.title;
  document.getElementById('evAttSub').textContent = info.cat + ' · ' + formatDate(info.ev.event_date) + (info.ev.event_time ? ' · ' + formatTime(info.ev.event_time) : '');
  tbody.innerHTML = '<tr><td colspan="4" class="empty-msg">Loading...</td></tr>';
  panel.scrollIntoView({ behavior: 'smooth' });

  const res = await apiCall('get_event_attendance', { event_id: eventId });
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="4" class="empty-msg">${escapeHtml(res.message)}</td></tr>`; return; }
  if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="4" class="empty-msg">No active youth members found.</td></tr>'; return; }
  tbody.innerHTML = res.data.map(m => {
    const status = m.status || 'absent';
    const timeVal = m.time_in ? m.time_in.substring(0, 5) : '';
    return `<tr data-member-id="${m.member_id}">
      <td>${escapeHtml(m.full_name)}</td>
      <td>${escapeHtml(m.slot_name || '—')}</td>
      <td><button type="button" class="status-toggle ${status}" data-status="${status}">${status === 'present' ? 'Present' : 'Absent'}</button></td>
      <td><input type="time" value="${timeVal}" ${status === 'absent' ? 'disabled' : ''}/></td>
    </tr>`;
  }).join('');
  bindAttendanceToggles(tbody);
}
